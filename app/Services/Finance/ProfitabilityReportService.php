<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\ItemLedgerEntryType;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PostedSalesInvoiceLine;
use App\Models\ValueEntry;
use Carbon\Carbon;

/**
 * Read-only profitability and item-cost projections from posted ledgers.
 *
 * Master-data costs and posted economic costs are deliberately returned as
 * separate measures so an indicative margin can never masquerade as profit.
 */
class ProfitabilityReportService
{
    /** @var array<int, array<string, mixed>> */
    private array $itemCache = [];

    /** @var array<string, array<string, float|null>> */
    private array $performanceCache = [];

    /**
     * @return array<string, float|null>
     */
    public function itemCostMeasures(Item $item, ?int $businessId = null): array
    {
        $cacheKey = $item->getKey().':'.($businessId ?? 'all');
        if (isset($this->itemCache[$cacheKey])) {
            return $this->itemCache[$cacheKey];
        }

        $entries = ItemLedgerEntry::query()
            ->where('item_id', $item->getKey())
            ->when($businessId !== null, fn ($query) => $query->where(function ($query) use ($businessId): void {
                $query->where('dimensions->business_id', $businessId);
            }))
            ->get();

        $valueEntries = ValueEntry::query()
            ->whereIn('item_ledger_entry_no', $entries->pluck('entry_number'))
            ->when($businessId !== null, fn ($query) => $query->where(function ($query) use ($businessId): void {
                $query->where('business_id', $businessId);
            }))
            ->get();

        $quantity = (float) $entries->sum('quantity');
        $inventoryValue = $valueEntries
            ->filter(fn (ValueEntry $entry): bool => ! $entry->expected_cost && (string) $entry->value_entry_state !== 'expected')
            ->sum(fn (ValueEntry $entry): float => $this->signedValue((float) $entry->cost_amount_actual, (float) $entry->quantity));

        $outputEntries = $entries->filter(fn (ItemLedgerEntry $entry): bool => $this->entryType($entry) === ItemLedgerEntryType::OUTPUT->value && (float) $entry->quantity > 0);
        $outputIds = $outputEntries->pluck('entry_number');
        $outputValues = $valueEntries->whereIn('item_ledger_entry_no', $outputIds)
            ->filter(fn (ValueEntry $entry): bool => ! $entry->expected_cost && (string) $entry->value_entry_state !== 'expected');
        $outputQuantity = (float) $outputEntries->sum('quantity');
        $productionValue = (float) $outputValues->sum(fn (ValueEntry $entry): float => abs((float) $entry->cost_amount_actual));

        return $this->itemCache[$cacheKey] = [
            'current_actual_inventory_cost' => abs($quantity) > 0.00000001 ? $inventoryValue / abs($quantity) : null,
            'last_actual_production_cost' => $outputValues->isNotEmpty() && $outputQuantity > 0
                ? (float) $outputValues->sortByDesc('posting_date')->first()->unit_cost ?: $productionValue / $outputQuantity
                : null,
            'average_actual_production_cost' => $outputQuantity > 0 ? $productionValue / $outputQuantity : null,
        ];
    }

    /**
     * @return array<string, float|null>
     */
    public function itemIndicativeMeasures(Item $item): array
    {
        $sellingPrice = $this->number($item->unit_price);
        $referenceCost = $this->number($item->standard_cost);
        $margin = $sellingPrice !== null && $referenceCost !== null ? $sellingPrice - $referenceCost : null;

        return [
            'selling_price' => $sellingPrice,
            'standard_reference_cost' => $referenceCost,
            'indicative_unit_margin' => $margin,
            'indicative_margin_percent' => $margin !== null && abs($sellingPrice) > 0.00000001 ? ($margin / $sellingPrice) * 100 : null,
            'markup_percent' => $margin !== null && abs($referenceCost) > 0.00000001 ? ($margin / $referenceCost) * 100 : null,
        ];
    }

    /**
     * Posted-sales performance for the current year-to-date item card.
     * Null amounts mean that no authoritative posted sales exist.
     *
     * @return array<string, float|null>
     */
    public function itemActualPerformance(Item $item, ?Carbon $from = null, ?Carbon $to = null, ?int $businessId = null): array
    {
        $from ??= now()->startOfYear();
        $to ??= now();
        $cacheKey = implode(':', [$item->getKey(), $from->toDateString(), $to->toDateString(), $businessId ?? 'all']);
        if (isset($this->performanceCache[$cacheKey])) {
            return $this->performanceCache[$cacheKey];
        }

        $lines = PostedSalesInvoiceLine::query()
            ->with('postedSalesInvoice')
            ->where('item_id', $item->getKey())
            ->whereHas('postedSalesInvoice', function ($query) use ($from, $to, $businessId): void {
                $query->where('cancelled', false)
                    ->whereBetween('posting_date', [$from->toDateString(), $to->toDateString()])
                    ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId));
            })
            ->get();

        if ($lines->isEmpty()) {
            return $this->performanceCache[$cacheKey] = [
                'quantity_sold' => null,
                'net_revenue' => null,
                'actual_cogs' => null,
                'gross_profit' => null,
                'gross_margin_percent' => null,
                'average_selling_price' => null,
                'average_actual_cost' => null,
            ];
        }

        $lineEntryNumbers = $lines->pluck('item_ledger_entry_id')->filter();
        $values = ValueEntry::query()
            ->whereIn('item_ledger_entry_no', ItemLedgerEntry::query()->whereIn('id', $lineEntryNumbers)->pluck('entry_number'))
            ->where('item_ledger_entry_type', 2)
            ->where('expected_cost', false)
            ->get();
        $cogs = abs((float) $values->sum('cost_amount_actual'));
        $revenue = (float) $lines->sum(function (PostedSalesInvoiceLine $line): float {
            $invoice = $line->postedSalesInvoice;
            $factor = (float) ($invoice?->currency_factor ?: 1);

            return abs((float) $line->line_amount) * $factor;
        });
        $quantity = (float) $lines->sum(fn (PostedSalesInvoiceLine $line): float => abs((float) ($line->quantity_base ?? $line->quantity)));
        $grossProfit = $revenue - $cogs;

        return $this->performanceCache[$cacheKey] = [
            'quantity_sold' => $quantity,
            'net_revenue' => $revenue,
            'actual_cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => abs($revenue) > 0.00000001 ? ($grossProfit / $revenue) * 100 : null,
            'average_selling_price' => $quantity > 0 ? $revenue / $quantity : null,
            'average_actual_cost' => $quantity > 0 && $cogs > 0 ? $cogs / $quantity : null,
        ];
    }

    private function entryType(ItemLedgerEntry $entry): string
    {
        return $entry->entry_type instanceof ItemLedgerEntryType
            ? $entry->entry_type->value
            : (string) $entry->entry_type;
    }

    private function signedValue(float $amount, float $quantity): float
    {
        return $quantity < 0 ? -abs($amount) : ($quantity > 0 ? abs($amount) : $amount);
    }

    private function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
