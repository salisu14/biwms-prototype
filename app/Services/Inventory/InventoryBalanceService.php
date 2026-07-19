<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Support\DecimalMath;

class InventoryBalanceService
{
    public function recalculateItem(int $itemId): string
    {
        $ledgerQuantity = DecimalMath::quantity(
            ItemLedgerEntry::query()
                ->where('item_id', $itemId)
                ->sum('quantity')
        );

        Item::query()
            ->whereKey($itemId)
            ->update(['inventory' => $ledgerQuantity]);

        return $ledgerQuantity;
    }
}
