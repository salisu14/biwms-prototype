<?php

namespace App\Services\Sales;

use App\Models\ItemLedgerEntry;
use App\Models\SalesOrderLine;

class StockValidationService
{
    /**
     * @throws \Exception
     */
    public function validate(SalesOrderLine $line): void
    {
        $available = ItemLedgerEntry::where('item_id', $line->item_id)
            ->where('location_id', $line->location_id)
            ->sum('remaining_quantity');

        if ($available < $line->quantity) {
            throw new \Exception(
                "Not enough stock for {$line->item_code}. Required: {$line->quantity}, Available: {$available}"
            );
        }
    }
}
