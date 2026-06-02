<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Models\FALedgerEntry;
use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FixedAssetListReportService
{
    /**
     * @param  array{
     *     as_of_date?: string|null,
     *     fa_class_id?: int|null,
     *     location_id?: int|null,
     *     depreciation_book_id?: int|null,
     *     status?: string|null
     * }  $filters
     * @return array{
     *     printed_at: string,
     *     filters: array<string, mixed>,
     *     summary: array<string, float|int>,
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function generate(array $filters = []): array
    {
        $asOfDate = filled($filters['as_of_date'] ?? null)
            ? Carbon::parse((string) $filters['as_of_date'])->endOfDay()
            : null;

        $query = FixedAsset::query()
            ->with(['faClass', 'location', 'depreciationBook', 'ledgerEntries'])
            ->when(
                filled($filters['fa_class_id'] ?? null),
                fn (Builder $builder): Builder => $builder->where('fa_class_id', (int) $filters['fa_class_id'])
            )
            ->when(
                filled($filters['location_id'] ?? null),
                fn (Builder $builder): Builder => $builder->where('location_id', (int) $filters['location_id'])
            )
            ->when(
                filled($filters['depreciation_book_id'] ?? null),
                fn (Builder $builder): Builder => $builder->where('depreciation_book_id', (int) $filters['depreciation_book_id'])
            )
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $builder): Builder => $builder->where('status', (string) $filters['status'])
            )
            ->when(
                $asOfDate !== null,
                fn (Builder $builder): Builder => $builder->where(function (Builder $nestedBuilder) use ($asOfDate): void {
                    $nestedBuilder
                        ->whereNull('acquisition_date')
                        ->orWhereDate('acquisition_date', '<=', $asOfDate->toDateString());
                })
            )
            ->orderBy('fa_no');

        $assets = $query->get();

        $rows = $assets->map(function (FixedAsset $asset) use ($asOfDate): array {
            $valuation = $this->resolveValuation($asset, $asOfDate);
            $remainingMonths = $this->resolveRemainingLifeMonths($asset, $asOfDate);

            return [
                'id' => $asset->id,
                'fa_no' => $asset->fa_no,
                'description' => $asset->description,
                'class' => $asset->faClass?->name,
                'location' => $asset->location?->name,
                'acquisition_date' => optional($asset->acquisition_date)?->toDateString(),
                'acquisition_cost' => $valuation['acquisition_cost'],
                'accumulated_depreciation' => $valuation['accumulated_depreciation'],
                'net_book_value' => $valuation['net_book_value'],
                'depreciation_method' => $asset->depreciation_method?->value,
                'depreciation_book' => $asset->depreciationBook?->code,
                'useful_life_remaining' => $remainingMonths,
                'useful_life_remaining_label' => $this->formatRemainingLife($remainingMonths),
                'status' => $asset->status?->value,
                'status_color' => $this->resolveStatusColor($asset->status?->value),
                'disposal_date' => optional($asset->disposal_date)?->toDateString(),
            ];
        })->all();

        return [
            'printed_at' => now()->format('Y-m-d H:i'),
            'as_of_date' => $asOfDate?->toDateString(),
            'filters' => $filters,
            'summary' => [
                'asset_count' => $assets->count(),
                'acquisition_cost' => (float) collect($rows)->sum('acquisition_cost'),
                'accumulated_depreciation' => (float) collect($rows)->sum('accumulated_depreciation'),
                'net_book_value' => (float) collect($rows)->sum('net_book_value'),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{acquisition_cost: float, accumulated_depreciation: float, net_book_value: float}
     */
    private function resolveValuation(FixedAsset $asset, ?Carbon $asOfDate): array
    {
        if ($asOfDate === null) {
            return [
                'acquisition_cost' => (float) $asset->acquisition_cost,
                'accumulated_depreciation' => (float) $asset->accumulated_depreciation,
                'net_book_value' => (float) $asset->net_book_value,
            ];
        }

        /** @var FALedgerEntry|null $latestEntry */
        $latestEntry = $asset->ledgerEntries
            ->filter(fn (FALedgerEntry $entry): bool => ! $entry->reversed && $entry->posting_date !== null && $entry->posting_date->endOfDay()->lessThanOrEqualTo($asOfDate))
            ->sortByDesc(fn (FALedgerEntry $entry): string => sprintf('%s-%09d', $entry->posting_date?->format('YmdHis') ?? '', $entry->entry_no))
            ->first();

        if ($latestEntry !== null) {
            $netBookValue = (float) $latestEntry->book_value_after;
            $accumulatedDepreciation = (float) $latestEntry->accumulated_depreciation;

            return [
                'acquisition_cost' => $netBookValue + $accumulatedDepreciation,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'net_book_value' => $netBookValue,
            ];
        }

        if ($asset->acquisition_date !== null && $asset->acquisition_date->endOfDay()->greaterThan($asOfDate)) {
            return [
                'acquisition_cost' => 0.0,
                'accumulated_depreciation' => 0.0,
                'net_book_value' => 0.0,
            ];
        }

        return [
            'acquisition_cost' => (float) $asset->acquisition_cost,
            'accumulated_depreciation' => 0.0,
            'net_book_value' => (float) $asset->acquisition_cost,
        ];
    }

    private function resolveRemainingLifeMonths(FixedAsset $asset, ?Carbon $asOfDate): ?float
    {
        if ($asset->depreciation_ending_date === null) {
            return null;
        }

        $referenceDate = $asOfDate?->copy() ?? now();

        return $referenceDate->diffInMonths($asset->depreciation_ending_date, false);
    }

    private function formatRemainingLife(?float $remainingMonths): string
    {
        if ($remainingMonths === null) {
            return 'N/A';
        }

        if ($remainingMonths <= 0) {
            return 'Expired';
        }

        $months = (int) round($remainingMonths);
        $yearsPart = intdiv($months, 12);
        $monthsPart = $months % 12;

        if ($yearsPart > 0 && $monthsPart > 0) {
            return "{$yearsPart}y {$monthsPart}m";
        }

        if ($yearsPart > 0) {
            return "{$yearsPart}y";
        }

        return "{$monthsPart}m";
    }

    private function resolveStatusColor(?string $status): string
    {
        return match ($status) {
            'disposed', 'sold' => 'danger',
            'dismantled', 'transferred' => 'warning',
            'active' => 'success',
            'under_construction' => 'info',
            default => 'gray',
        };
    }
}
