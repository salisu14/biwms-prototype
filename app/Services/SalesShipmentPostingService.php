<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesShipmentHeader;
use App\Models\SalesShipmentLine;
use App\Models\ItemLedgerEntry;
use App\Enums\ShipmentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesShipmentPostingService
{
    private NumberSeriesService $numberSeries;
    private InventoryPostingService $inventoryPosting;
    private DimensionManagementService $dimensionService;
    private ItemTrackingManagementService $itemTrackingService;

    public function __construct(
        NumberSeriesService $numberSeries,
        InventoryPostingService $inventoryPosting,
        DimensionManagementService $dimensionService,
        ItemTrackingManagementService $itemTrackingService
    ) {
        $this->numberSeries = $numberSeries;
        $this->inventoryPosting = $inventoryPosting;
        $this->dimensionService = $dimensionService;
        $this->itemTrackingService = $itemTrackingService;
    }

    /**
     * Post Sales Order Shipment (mirrors BC Codeunit 80 "Sales-Post")
     *
     * @param SalesOrder $salesOrder
     * @param array $shipmentLines ['line_id' => qty_to_ship]
     * @param array $options ['posting_date', 'shipping_agent_code', 'package_tracking_no']
     * @return SalesShipmentHeader
     * @throws \Exception
     */
    public function postShipment(
        SalesOrder $salesOrder,
        array $shipmentLines = [],
        array $options = []
    ): SalesShipmentHeader {
        return DB::transaction(function() use ($salesOrder, $shipmentLines, $options) {

            // 1. Validate posting
            $this->validatePosting($salesOrder, $shipmentLines);

            // 2. Create Posted Document (Sales Shipment Header)
            $shipmentHeader = $this->createShipmentHeader($salesOrder, $options);

            // 3. Process Lines
            foreach ($salesOrder->lines as $orderLine) {
                $qtyToShip = $shipmentLines[$orderLine->id] ?? $orderLine->qty_to_ship;

                if ($qtyToShip <= 0) continue;

                $this->postShipmentLine($shipmentHeader, $orderLine, $qtyToShip);
            }

            // 4. Update Sales Order status
            $this->updateOrderAfterPosting($salesOrder);

            // 5. Create Item Ledger Entries (Inventory reduction)
            $this->inventoryPosting->postShipmentInventory($shipmentHeader);

            Log::info("Sales Shipment Posted", [
                'shipment_no' => $shipmentHeader->document_no,
                'order_no' => $salesOrder->order_no
            ]);

            return $shipmentHeader->fresh('lines');
        });
    }

    private function validatePosting(SalesOrder $order, array $shipmentLines): void
    {
        if ($order->status !== 'released') {
            throw new \InvalidArgumentException("Order must be released before posting");
        }

        foreach ($order->lines as $line) {
            $qtyToShip = $shipmentLines[$line->id] ?? $line->qty_to_ship;

            if ($qtyToShip > $line->outstanding_quantity) {
                throw new \InvalidArgumentException(
                    "Qty. to Ship exceeds Outstanding Qty on line {$line->line_no}"
                );
            }

            // BC: Check Item Tracking if required
            if ($line->requiresItemTracking() && $qtyToShip > 0) {
                $trackedQty = $this->itemTrackingService->getTrackedQuantity($line);
                if ($trackedQty < $qtyToShip) {
                    throw new \InvalidArgumentException(
                        "Item Tracking missing for line {$line->line_no}. Tracked: {$trackedQty}, To Ship: {$qtyToShip}"
                    );
                }
            }
        }
    }

    private function createShipmentHeader(SalesOrder $order, array $options): SalesShipmentHeader
    {
        $postingDate = $options['posting_date'] ?? now();

        return SalesShipmentHeader::create([
            'document_no' => $this->numberSeries->getNextNo('S-SHIP'),
            'sales_order_id' => $order->id,
            'order_no' => $order->order_no,

            // Customer Info (Snapshot)
            'sell_to_customer_no' => $order->sell_to_customer_no,
            'sell_to_customer_name' => $order->sell_to_customer_name,
            'sell_to_address' => $order->sell_to_address,
            'sell_to_city' => $order->sell_to_city,
            'sell_to_post_code' => $order->sell_to_post_code,
            'bill_to_customer_no' => $order->bill_to_customer_no,
            'ship_to_code' => $order->ship_to_code,
            'ship_to_name' => $order->ship_to_name,

            // Dates
            'order_date' => $order->order_date,
            'posting_date' => $postingDate,
            'shipment_date' => $order->shipment_date,
            'document_date' => $options['document_date'] ?? $postingDate,

            // Shipping
            'shipment_method_code' => $options['shipment_method_code'] ?? $order->shipment_method_code,
            'shipping_agent_code' => $options['shipping_agent_code'] ?? $order->shipping_agent_code,
            'shipping_agent_service_code' => $order->shipping_agent_service_code,
            'package_tracking_no' => $options['package_tracking_no'] ?? $order->package_tracking_no,
            'transport_method' => $order->transport_method,

            // Financial
            'currency_code' => $order->currency_code,
            'currency_factor' => $order->currency_factor,
            'prices_including_vat' => $order->prices_including_vat,
            'customer_posting_group' => $order->customer_posting_group,

            // Dimensions
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
            'dimension_set_id' => $order->dimension_set_id,

            'location_code' => $order->location_code,
            'salesperson_code' => $order->salesperson_code,
            'external_document_no' => $order->external_document_no,
            'user_id' => auth()->id(),
        ]);
    }

    private function postShipmentLine(
        SalesShipmentHeader $header,
        SalesOrderLine $orderLine,
        float $qtyToShip
    ): void {

        $lineAmount = $qtyToShip * $orderLine->unit_price * (1 - $orderLine->line_discount_pct/100);

        $shipmentLine = SalesShipmentLine::create([
            'sales_shipment_header_id' => $header->id,
            'document_no' => $header->document_no,
            'line_no' => $orderLine->line_no,
            'sales_order_line_id' => $orderLine->id,

            // Item/Account Info
            'type' => $orderLine->type,
            'no' => $orderLine->no,
            'variant_code' => $orderLine->variant_code,
            'description' => $orderLine->description,
            'description_2' => $orderLine->description_2,

            // Quantities
            'quantity' => $qtyToShip,
            'quantity_base' => $qtyToShip * $orderLine->qty_per_unit_of_measure,
            'qty_shipped_not_invoiced' => $qtyToShip, // Full qty awaiting invoicing

            // UOM
            'unit_of_measure' => $orderLine->unit_of_measure,
            'unit_of_measure_code' => $orderLine->unit_of_measure_code,
            'qty_per_unit_of_measure' => $orderLine->qty_per_unit_of_measure,

            // Pricing
            'unit_price' => $orderLine->unit_price,
            'unit_cost' => $orderLine->unit_cost,
            'unit_cost_lcy' => $orderLine->unit_cost_lcy,
            'line_discount_pct' => $orderLine->line_discount_pct,
            'line_amount' => $lineAmount,
            'amount' => $lineAmount,

            // Tracking
            'order_no' => $orderLine->order_no,
            'order_line_no' => $orderLine->line_no,
            'drop_shipment' => $orderLine->drop_shipment,

            // Location/Warehouse
            'location_code' => $orderLine->location_code,
            'bin_code' => $orderLine->bin_code,

            // Dimensions
            'shortcut_dimension_1_code' => $orderLine->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $orderLine->shortcut_dimension_2_code,
            'dimension_set_id' => $orderLine->dimension_set_id,

            'shipment_date' => $header->shipment_date,
            'posting_date' => $header->posting_date,
        ]);

        // Copy Item Tracking
        if ($orderLine->requiresItemTracking()) {
            $this->itemTrackingService->copyTrackingToShipment(
                $orderLine,
                $shipmentLine,
                $qtyToShip
            );
        }

        // Update Order Line
        $orderLine->update([
            'quantity_shipped' => $orderLine->quantity_shipped + $qtyToShip,
            'qty_shipped_not_invoiced' => $orderLine->qty_shipped_not_invoiced + $qtyToShip,
            'outstanding_quantity' => $orderLine->outstanding_quantity - $qtyToShip,
        ]);
    }

    private function updateOrderAfterPosting(SalesOrder $order): void
    {
        $allShipped = $order->lines->every(
            fn($line) => $line->outstanding_quantity <= 0
        );

        $order->update([
            'status' => $allShipped ? ShipmentStatus::Shipped->value : ShipmentStatus::PartiallyShipped->value,
            'completely_shipped' => $allShipped,
        ]);
    }
}
