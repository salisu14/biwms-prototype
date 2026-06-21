<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\ItemLedgerEntry;

class ItemLedgerSummaryService
{
    /**
     * @param  array{entry_type?: string|null, month_filter?: string|null, item_id?: int|null, location_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters = []): array
    {
        $entryType = filled($filters['entry_type'] ?? null) ? (string) $filters['entry_type'] : null;
        $monthFilter = filled($filters['month_filter'] ?? null) ? (string) $filters['month_filter'] : null;
        $itemId = filled($filters['item_id'] ?? null) ? (int) $filters['item_id'] : null;
        $locationId = filled($filters['location_id'] ?? null) ? (int) $filters['location_id'] : null;

        $entries = ItemLedgerEntry::query()
            ->with(['item', 'location'])
            ->when($entryType !== null, fn ($query) => $query->where('entry_type', $entryType))
            ->when($monthFilter !== null, fn ($query) => $query->whereRaw("to_char(posting_date, 'YYYY-MM') = ?", [$monthFilter]))
            ->when($itemId !== null, fn ($query) => $query->where('item_id', $itemId))
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->orderByDesc('posting_date')
            ->orderByDesc('entry_number')
            ->get();

        $entryTypeSummary = $entries
            ->groupBy(fn (ItemLedgerEntry $entry): string => (string) $entry->entry_type->value)
            ->map(fn ($group, $type): array => [
                'type' => (string) $type,
                'count' => $group->count(),
                'quantity' => (float) $group->sum('quantity'),
                'remaining_quantity' => (float) $group->sum('remaining_quantity'),
                'cost' => (float) $group->sum('cost_amount_actual'),
            ])
            ->sortBy('type')
            ->values()
            ->all();

        $monthBuckets = $entries
            ->groupBy(fn (ItemLedgerEntry $entry): string => optional($entry->posting_date)?->format('Y-m') ?? 'unknown')
            ->map(fn ($group, $bucket): array => [
                'bucket' => (string) $bucket,
                'count' => $group->count(),
                'quantity' => (float) $group->sum('quantity'),
                'cost' => (float) $group->sum('cost_amount_actual'),
            ])
            ->sortBy('bucket')
            ->values()
            ->all();

        return [
            'entries' => $entries,
            'entryTypeSummary' => $entryTypeSummary,
            'monthBuckets' => $monthBuckets,
            'summary' => [
                'count' => $entries->count(),
                'quantity' => (float) $entries->sum('quantity'),
                'remaining_quantity' => (float) $entries->sum('remaining_quantity'),
                'cost' => (float) $entries->sum('cost_amount_actual'),
            ],
        ];
    }
}
