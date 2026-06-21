<?php

namespace App\Services\Sales;

use App\Models\Item;
use App\Models\SalesOrderLine;

class SalesOrderLineService
{
    /**
     * Initialize line (used on create)
     */
    public function initialize(SalesOrderLine $line): SalesOrderLine
    {
        // Copy from item
        if ($line->item_id && ! $line->general_product_posting_group_id) {
            $item = Item::find($line->item_id);

            if ($item) {
                $line->general_product_posting_group_id = $item->general_product_posting_group_id;
                $line->inventory_posting_group_id = $item->inventory_posting_group_id;
                $line->unit_cost = $item->unit_cost;
            }
        }

        // Default quantity to ship
        $line->quantity_to_ship = $line->quantity;

        return $this->recalculate($line);
    }

    /**
     * Recalculate amounts
     */
    public function recalculate(SalesOrderLine $line): SalesOrderLine
    {
        $line->line_total = $line->quantity * $line->unit_price;

        $line->line_discount_amount = $line->line_total *
            ($line->line_discount_percent / 100);

        $line->line_amount = $line->line_total - $line->line_discount_amount;

        $line->vat_amount = $line->line_amount *
            ($line->vat_percentage / 100);

        $line->amount_including_vat = $line->line_amount + $line->vat_amount;

        $line->quantity_to_ship = $line->quantity - $line->quantity_shipped;

        return $line;
    }

    /**
     * Update quantities after shipment
     */
    public function updateShipment(SalesOrderLine $line, float $qty): SalesOrderLine
    {
        $line->quantity_shipped += $qty;

        if ($line->quantity_shipped >= $line->quantity) {
            $line->line_status = 'SHIPPED';
        } elseif ($line->quantity_shipped > 0) {
            $line->line_status = 'PARTIALLY_SHIPPED';
        }

        return $this->recalculate($line);
    }

    /**
     * Update quantities after invoicing
     */
    public function updateInvoicing(SalesOrderLine $line, float $qty): SalesOrderLine
    {
        $line->quantity_invoiced += $qty;

        if ($line->quantity_invoiced >= $line->quantity) {
            $line->line_status = 'INVOICED';
        } elseif ($line->quantity_invoiced > 0) {
            $line->line_status = 'PARTIALLY_INVOICED';
        }

        return $line;
    }
}
