<?php

namespace App\Services\Posting;

use App\Models\ChartOfAccount;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\Location;
use Exception;

class InventoryPostingResolverService
{
    public function resolveInventoryAccount(Item $item, ?Location $location = null): ChartOfAccount
    {
        $setup = $this->resolveSetupWithAccount(
            inventoryPostingGroupId: (int) $item->inventory_posting_group_id,
            locationId: $location?->id,
            accountColumn: 'inventory_account_id'
        );

        if (! $setup?->inventoryAccount) {
            throw new Exception("Inventory account missing for item {$item->item_code}");
        }

        return $setup->inventoryAccount;
    }

    public function resolveWipAccount(int $inventoryPostingGroupId, ?Location $location = null): ChartOfAccount
    {
        $setup = $this->resolveSetupWithAccount(
            inventoryPostingGroupId: $inventoryPostingGroupId,
            locationId: $location?->id,
            accountColumn: 'wip_account_id'
        );

        if (! $setup?->wipAccount) {
            throw new Exception("WIP account missing for inventory posting group {$inventoryPostingGroupId}");
        }

        return $setup->wipAccount;
    }

    public function resolveSetupWithAccount(
        int $inventoryPostingGroupId,
        ?int $locationId,
        string $accountColumn
    ): ?InventoryPostingSetup {
        $query = InventoryPostingSetup::query()
            ->where('inventory_posting_group_id', $inventoryPostingGroupId)
            ->whereNotNull($accountColumn);

        if ($locationId) {
            return (clone $query)
                ->where(function ($builder) use ($locationId): void {
                    $builder->where('location_id', $locationId)
                        ->orWhereNull('location_id');
                })
                ->orderByRaw('location_id IS NULL')
                ->first()
                ?? $query->first();
        }

        return $query
            ->whereNull('location_id')
            ->first()
            ?? $query->first();
    }
}
