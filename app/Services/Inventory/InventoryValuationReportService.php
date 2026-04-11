<?php

namespace App\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryValuationReportService
{
    public function generate($startDate, $endDate, $filters = []): Collection
    {
        $query = DB::table('inventory_ledgers')
            ->select('item_id')

            ->selectRaw('
                SUM(CASE WHEN posting_date < ? THEN quantity ELSE 0 END) as opening_qty,
                SUM(CASE WHEN posting_date < ? THEN cost_amount ELSE 0 END) as opening_value
            ', [$startDate, $startDate])

            ->selectRaw("
                SUM(CASE WHEN entry_type = 'purchase' AND posting_date BETWEEN ? AND ? THEN quantity ELSE 0 END) as purchase_qty,
                SUM(CASE WHEN entry_type = 'purchase' AND posting_date BETWEEN ? AND ? THEN cost_amount ELSE 0 END) as purchase_value
            ", [$startDate, $endDate, $startDate, $endDate])

            ->selectRaw("
                SUM(CASE WHEN entry_type = 'sale' AND posting_date BETWEEN ? AND ? THEN quantity ELSE 0 END) as sales_qty,
                SUM(CASE WHEN entry_type = 'sale' AND posting_date BETWEEN ? AND ? THEN cost_amount ELSE 0 END) as sales_value
            ", [$startDate, $endDate, $startDate, $endDate])

            ->groupBy('item_id');

        return $query->get()->map(function ($row) {

            $closingQty = $row->opening_qty
                + $row->purchase_qty
                - $row->sales_qty;

            $closingValue = $row->opening_value
                + $row->purchase_value
                - $row->sales_value;

            $unitCost = $closingQty != 0
                ? $closingValue / $closingQty
                : 0;

            return [
                'item_id' => $row->item_id,
                'opening_qty' => $row->opening_qty,
                'closing_qty' => $closingQty,
                'closing_value' => $closingValue,
                'unit_cost' => $unitCost,
            ];
        });
    }
}
