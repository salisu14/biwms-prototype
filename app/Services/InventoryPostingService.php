<?php

namespace App\Services;

use App\Models\SalesShipmentHeader;
use App\Models\ItemLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Handles inventory posting for shipments (BC Item Journal / Item Ledger Entry creation)
 */
class InventoryPostingService
{
    public function postShipmentInventory(SalesShipmentHeader $shipment): void
    {
        foreach ($shipment->lines as $line) {
            if (!$line->isItem()) continue;

            // Create Item Ledger Entry (BC Table 32)
            ItemLedgerEntry::create([
                'entry_type' => 'sale',
                'document_type' => 'sales_shipment',
                'document_no' => $shipment->document_no,
                'document_line_no' => $line->line_no,
                'item_no' => $line->no,
                'variant_code' => $line->variant_code,
                'location_code' => $line->location_code,
                'quantity' => -$line->quantity, // Negative for outbound
                'quantity_base' => -$line->quantity_base,
                'unit_of_measure_code' => $line->unit_of_measure_code,
                'qty_per_unit_of_measure' => $line->qty_per_unit_of_measure,
                'posting_date' => $shipment->posting_date,
                'document_date' => $shipment->document_date,
                'cost_amount_actual' => $line->unit_cost * $line->quantity,
                'sales_amount_expected' => $line->unit_price * $line->quantity,
                'customer_no' => $shipment->sell_to_customer_no,
                'source_type' => 'customer',
                'source_no' => $shipment->sell_to_customer_no,
                'serial_no' => $line->serial_no,
                'lot_no' => $line->lot_no,
                'order_type' => 'sales',
                'order_no' => $line->order_no,
                'order_line_no' => $line->order_line_no,
            ]);

            // Update inventory quantity
            $this->updateInventory($line);
        }
    }

    private function updateInventory($shipmentLine): void
    {
        // Logic to update item availability/quantity on hand
        // This would interface with your Inventory service
    }
}
