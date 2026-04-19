<?php

namespace App\Services;

use App\Enums\ItemLedgerEntryType;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InventoryReportService
{
    /**
     * Get the inventory movement summary for all items within a date range.
     */
    public function getMovementSummary(Carbon $startDate, Carbon $endDate, ?int $locationId = null): Builder
    {
        $query = Item::query()
            ->with(['uoms'])
            ->select('items.*');

        // Opening Balance (Qty & Value)
        $query->addSelect([
            'opening_qty' => ItemLedgerEntry::selectRaw('COALESCE(SUM(quantity), 0)')
                ->whereColumn('item_id', 'items.id')
                ->where('posting_date', '<', $startDate)
                ->when($locationId, fn ($q) => $q->where('location_id', $locationId)),

            'opening_value' => ItemLedgerEntry::selectRaw('COALESCE(SUM(cost_amount_actual), 0)')
                ->whereColumn('item_id', 'items.id')
                ->where('posting_date', '<', $startDate)
                ->when($locationId, fn ($q) => $q->where('location_id', $locationId)),
        ]);

        // movements during period
        $this->addMovementColumns($query, $startDate, $endDate, $locationId);

        return $query;
    }

    protected function addMovementColumns(Builder $query, Carbon $startDate, Carbon $endDate, ?int $locationId): void
    {
        $types = [
            'purchase_in' => [ItemLedgerEntryType::PURCHASE, '>'],
            'purchase_out' => [ItemLedgerEntryType::PURCHASE, '<='],
            'sale_out' => [ItemLedgerEntryType::SALE, '<'],
            'sale_in' => [ItemLedgerEntryType::SALE, '>='],
            'transfer' => [ItemLedgerEntryType::TRANSFER, null],
            'pos_adj' => [ItemLedgerEntryType::POSITIVE_ADJUSTMENT, null],
            'neg_adj' => [ItemLedgerEntryType::NEGATIVE_ADJUSTMENT, null],
        ];

        foreach ($types as $key => $config) {
            $type = $config[0];
            $qtyCheck = $config[1];

            // Qty
            $query->addSelect([
                "{$key}_qty" => ItemLedgerEntry::selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('item_id', 'items.id')
                    ->whereBetween('posting_date', [$startDate, $endDate])
                    ->where('entry_type', $type->value)
                    ->when($qtyCheck, fn ($q) => $q->where('quantity', $qtyCheck, 0))
                    ->when($locationId, fn ($q) => $q->where('location_id', $locationId)),

                "{$key}_value" => ItemLedgerEntry::selectRaw('COALESCE(SUM(cost_amount_actual), 0)')
                    ->whereColumn('item_id', 'items.id')
                    ->whereBetween('posting_date', [$startDate, $endDate])
                    ->where('entry_type', $type->value)
                    ->when($qtyCheck, fn ($q) => $q->where('quantity', $qtyCheck, 0))
                    ->when($locationId, fn ($q) => $q->where('location_id', $locationId)),
            ]);
        }
    }
}
