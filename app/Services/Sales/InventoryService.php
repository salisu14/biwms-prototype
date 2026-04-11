<?php

namespace App\Services\Sales;

use App\Models\InventoryItem;

class InventoryService
{
    public function consume($itemId, $qty)
    {
        $layers = InventoryItem::where('item_id', $itemId)
            ->where('quantity', '>', 0)
            ->orderBy('created_at') // FIFO
            ->get();

        $remaining = $qty;
        $totalCost = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($layer->quantity, $remaining);

            $totalCost += $take * $layer->unit_cost;

            $layer->decrement('quantity', $take);

            $remaining -= $take;
        }

        return $totalCost;
    }
}
