<?php

namespace App\Services\Sales;

use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\WarehouseShipment;
use App\Services\PostingService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        protected PostingService $postingService,
        protected PricingService $pricingService
    ) {}

    /**
     * Submit for approval
     */
    public function submitForApproval(SalesOrder $order): SalesOrder
    {
        $order->submitForApproval(); // let model handle guards
        return $order;
    }

    /**
     * Approve order
     */
    public function approve(SalesOrder $order, int $userId): SalesOrder
    {
        $order->approve($userId); // model updates status, approved_by, approved_at
        return $order;
    }

    /**
     * Release order to warehouse
     */
    public function release(SalesOrder $order): SalesOrder
    {
        $order->release(); // model handles status checks
        return $order;
    }

    /**
     * Cancel order
     */
    public function cancel(SalesOrder $order, int $userId, string $reason): SalesOrder
    {
        $order->cancel($userId, $reason); // model handles status validation
        return $order;
    }

    /**
     * Add a line to the order with automatic pricing
     */
    public function addLine(
        SalesOrder $order,
        Item $item,
        float $quantity,
        ?string $variantCode = null,
        ?string $uom = null,
        ?\DateTime $requestedDeliveryDate = null
    ): SalesOrderLine {
        $priceData = $this->pricingService->getSalesPrice(
            item: $item,
            customer: $order->customer,
            quantity: $quantity,
            variantCode: $variantCode,
            uom: $uom ?? $item->base_unit_of_measure,
            location: $order->location,
            date: $order->order_date
        );

        $line = $order->lines()->create([
            'line_number' => $order->nextLineNumber(),
            'item_id' => $item->id,
            'item_code' => $item->item_number,
            'description' => $item->description,
            'variant_code' => $variantCode,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
            'quantity' => $quantity,
            'unit_of_measure_code' => $uom ?? $item->base_unit_of_measure,
            'qty_per_unit_of_measure' => 1,
            'quantity_base' => $quantity,
            'unit_price' => $priceData['unit_price'],
            'unit_cost' => $item->unit_cost,
            'line_discount_percent' => $priceData['discount_percent'],
            'requested_delivery_date' => $requestedDeliveryDate,
            'location_id' => $order->location_id,
            'price_source' => $priceData['price_source'],
            'pricing_master_id' => $priceData['pricing_master_id'],
        ]);

        $order->recalculateTotals(); // model handles totals automatically

        return $line;
    }

    /**
     * Create warehouse shipment from order
     */
    public function createShipment(SalesOrder $order, ?int $userId = null): WarehouseShipment
    {
        return $order->createShipment($userId); // model handles status checks, shipment lines, and progress
    }

    /**
     * Post Sales Invoice
     */
    public function postInvoice(
        SalesOrder $order,
        array $shipmentIds = [],
        ?\DateTime $postingDate = null,
        ?string $documentNumber = null
    ): void {
        $postingDate = $postingDate ?? now();
        DB::transaction(function () use ($order, $shipmentIds, $postingDate, $documentNumber) {
            foreach ($order->getLinesToInvoice($shipmentIds) as $lineData) {
                $soLine = $lineData['so_line'];
                $quantity = $lineData['quantity'];
                $this->postingService->postSale(
                    customer: $order->customer,
                    item: $soLine->item,
                    quantity: $quantity,
                    unitPrice: $soLine->unit_price,
                    unitCost: $soLine->unit_cost,
                    postingDate: $postingDate,
                    documentNumber: $documentNumber ?? 'INV-' . $order->id
                );

                $soLine->updateInvoicedProgress($quantity);
            }

            $order->updateInvoiceStatus();
        });
    }

    protected function getSalesOrderService(): SalesOrderService
    {
        return app(SalesOrderService::class);
    }
}
