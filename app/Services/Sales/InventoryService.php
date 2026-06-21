<?php

namespace App\Services\Sales;

use App\Models\ItemLedgerEntry;

class InventoryService
{
    public function consume($itemId, $qty)
    {
        $layers = ItemLedgerEntry::where('item_id', $itemId)
            ->where('open', true)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('posting_date') // FIFO
            ->get();

        $remaining = $qty;
        $totalCost = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($layer->remaining_quantity, $remaining);

            $totalCost += $take * $layer->cost_amount_actual / $layer->quantity;

            $layer->decrement('remaining_quantity', $take);
            if ($layer->remaining_quantity <= 0) {
                $layer->update(['open' => false]);
            }

            $remaining -= $take;
        }

        return $totalCost;
    }
}
