<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use Illuminate\Support\Collection;

class InventoryValuationReportService
{
    /**
     * Build valuation from the canonical quantity and value ledgers.
     *
     * ILE quantities are authoritative for stock. Value Entries are
     * authoritative for inventory value and retain expected/actual and
     * adjustment rows, including append-only reversals.
     *
     * @param  array{item_id?: int|null, location_id?: int|null, business_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function generate($startDate, $endDate, array $filters = []): Collection
    {
        $start = $this->dateString($startDate);
        $end = $this->dateString($endDate);

        $entries = ItemLedgerEntry::query()
            ->with(['item.baseUom', 'location'])
            ->whereDate('posting_date', '<=', $end)
            ->when($filters['item_id'] ?? null, fn ($query, int $itemId) => $query->where('item_id', $itemId))
            ->when($filters['location_id'] ?? null, fn ($query, int $locationId) => $query->where('location_id', $locationId))
            ->when($filters['business_id'] ?? null, function ($query, int $businessId): void {
                $query->where('business_id', $businessId);
            })
            ->orderBy('item_id')
            ->orderBy('location_id')
            ->orderBy('posting_date')
            ->orderBy('entry_number')
            ->get();

        $values = ValueEntry::query()
            ->whereIn('item_ledger_entry_no', $entries->pluck('entry_number'))
            ->whereDate('posting_date', '<=', $end)
            ->when($filters['business_id'] ?? null, function ($query, int $businessId): void {
                $query->where('business_id', $businessId);
            })
            ->get()
            ->groupBy(fn (ValueEntry $value): string => (string) $value->item_ledger_entry_no);

        return $entries
            ->groupBy(fn (ItemLedgerEntry $entry): string => $entry->item_id.':'.($entry->location_id ?? 'null'))
            ->map(fn (Collection $itemEntries): array => $this->summarizeGroup($itemEntries, $values, $start, $end))
            ->values();
    }

    private function dateString(mixed $date): string
    {
        return is_object($date) && method_exists($date, 'toDateString') ? $date->toDateString() : (string) $date;
    }

    /** @param Collection<int, ItemLedgerEntry> $entries */
    private function summarizeGroup(Collection $entries, Collection $values, string $start, string $end): array
    {
        /** @var ItemLedgerEntry $first */
        $first = $entries->first();
        $openingEntries = $entries->filter(fn (ItemLedgerEntry $entry): bool => (string) $entry->posting_date < $start);
        $periodEntries = $entries->filter(fn (ItemLedgerEntry $entry): bool => (string) $entry->posting_date >= $start && (string) $entry->posting_date <= $end);
        $allValues = $entries->flatMap(fn (ItemLedgerEntry $entry): Collection => $values->get((string) $entry->entry_number, collect()));
        $openingValues = $allValues->filter(fn (ValueEntry $value): bool => (string) $value->posting_date < $start);
        $periodValues = $allValues->filter(fn (ValueEntry $value): bool => (string) $value->posting_date >= $start && (string) $value->posting_date <= $end);

        $openingActual = $this->sumValue($openingValues, true);
        $openingExpected = $this->sumValue($openingValues, false);
        $periodActual = $this->sumValue($periodValues, true);
        $periodExpected = $this->sumValue($periodValues, false);
        $adjustmentValue = $periodValues
            ->filter(fn (ValueEntry $value): bool => in_array((string) $value->value_entry_state, ['adjustment', 'reversal'], true) || in_array(strtoupper((string) $value->entry_type), ['REVALUATION', 'COST_ADJUSTMENT'], true))
            ->sum(fn (ValueEntry $value): float => $this->signedValue((float) $value->cost_amount_actual, $value));
        $closingQuantity = (float) $entries->sum('quantity');
        $closingValue = $openingActual + $openingExpected + $periodActual + $periodExpected;

        return [
            'item_id' => (int) $first->item_id,
            'location_id' => $first->location_id !== null ? (int) $first->location_id : null,
            'item_code' => (string) ($first->item?->item_code ?? $first->item_id),
            'description' => (string) ($first->item?->description ?? ''),
            'base_unit_of_measure' => $first->item?->baseUom?->uom_code,
            'opening_qty' => round((float) $openingEntries->sum('quantity'), 8),
            'period_qty' => round((float) $periodEntries->sum('quantity'), 8),
            'closing_qty' => round($closingQuantity, 8),
            'quantity_on_hand' => round($closingQuantity, 8),
            'remaining_quantity' => round((float) $entries->sum('remaining_quantity'), 8),
            'opening_expected_cost' => round($openingExpected, 4),
            'expected_cost' => round($periodExpected, 4),
            'opening_actual_cost' => round($openingActual, 4),
            'actual_cost' => round($periodActual, 4),
            'adjustment_value' => round((float) $adjustmentValue, 4),
            'inventory_value' => round($closingValue, 4),
            'closing_value' => round($closingValue, 4),
            'unit_cost' => abs($closingQuantity) > 0.00000001 ? round($closingValue / abs($closingQuantity), 8) : 0.0,
            'purchase_qty' => $this->quantityForTypes($periodEntries, ['Purchase']),
            'purchase_value' => $this->valueForTypes($periodValues, [1]),
            'sales_qty' => $this->quantityForTypes($periodEntries, ['Sale']),
            'sales_value' => abs($this->valueForTypes($periodValues, [2])),
        ];
    }

    /** @param Collection<int, ValueEntry> $values */
    private function sumValue(Collection $values, bool $actual): float
    {
        return (float) $values->sum(function (ValueEntry $value) use ($actual): float {
            $isActual = ! $value->expected_cost && (string) $value->value_entry_state !== 'expected';
            if ($actual !== $isActual) {
                return 0.0;
            }

            return $this->signedValue((float) ($actual ? $value->cost_amount_actual : $value->cost_amount_expected), $value);
        });
    }

    /** @param Collection<int, ItemLedgerEntry> $entries */
    private function quantityForTypes(Collection $entries, array $types): float
    {
        return round((float) $entries->filter(fn (ItemLedgerEntry $entry): bool => in_array((string) $entry->entry_type->value, $types, true))->sum('quantity'), 8);
    }

    /** @param Collection<int, ValueEntry> $values */
    private function valueForTypes(Collection $values, array $types): float
    {
        return round((float) $values->filter(fn (ValueEntry $value): bool => in_array((int) $value->item_ledger_entry_type, $types, true))->sum(fn (ValueEntry $value): float => $this->signedValue((float) $value->cost_amount_actual, $value)), 4);
    }

    private function signedValue(float $amount, ValueEntry $value): float
    {
        $quantity = (float) ($value->quantity ?? 0);
        if ($quantity < 0) {
            return -abs($amount);
        }
        if ($quantity > 0) {
            return abs($amount);
        }

        return $amount;
    }
}
