<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\FAPostingType;
use App\Models\FALedgerEntry;
use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DepreciationBookReportService
{
    public function __construct(
        private readonly DepreciationCalculationService $depreciationCalculationService
    ) {}

    /**
     * @param  array{
     *     as_of_date?: string|null,
     *     fa_class_id?: int|null,
     *     location_id?: int|null,
     *     depreciation_book_id?: int|null
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
            ->whereNotNull('depreciation_book_id')
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
            $annualDepreciation = $this->estimateAnnualDepreciation($asset, $asOfDate);
            $nextDepreciationDate = $this->resolveNextDepreciationDate($asset, $asOfDate);

            return [
                'id' => $asset->id,
                'fa_no' => $asset->fa_no,
                'description' => $asset->description,
                'book_code' => $asset->depreciationBook?->code,
                'book_description' => $asset->depreciationBook?->description,
                'method' => $asset->depreciation_method?->value,
                'class' => $asset->faClass?->name,
                'location' => $asset->location?->name,
                'annual_depreciation' => $annualDepreciation,
                'accumulated_depreciation' => $valuation['accumulated_depreciation'],
                'remaining_book_value' => $valuation['remaining_book_value'],
                'next_depreciation_date' => $nextDepreciationDate?->toDateString(),
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
                'annual_depreciation' => (float) collect($rows)->sum('annual_depreciation'),
                'accumulated_depreciation' => (float) collect($rows)->sum('accumulated_depreciation'),
                'remaining_book_value' => (float) collect($rows)->sum('remaining_book_value'),
            ],
            'rows' => $rows,
        ];
    }

    private function estimateAnnualDepreciation(FixedAsset $asset, ?Carbon $asOfDate): float
    {
        if (! $asset->canDepreciate()) {
            return 0.0;
        }

        $referenceDate = $asOfDate?->copy() ?? now();
        $yearStart = $referenceDate->copy()->startOfYear()->toDateTime();
        $yearEnd = $referenceDate->copy()->endOfYear()->toDateTime();

        return (float) $this->depreciationCalculationService->calculate($asset, $yearStart, $yearEnd);
    }

    private function resolveNextDepreciationDate(FixedAsset $asset, ?Carbon $asOfDate): ?Carbon
    {
        if (! $asset->canDepreciate()) {
            return null;
        }

        /** @var FALedgerEntry|null $lastDepreciationEntry */
        $lastDepreciationEntry = $asset->ledgerEntries
            ->filter(function (FALedgerEntry $entry) use ($asOfDate): bool {
                if ($entry->reversed || $entry->fa_posting_type !== FAPostingType::DEPRECIATION->value || $entry->posting_date === null) {
                    return false;
                }

                if ($asOfDate === null) {
                    return true;
                }

                return $entry->posting_date->endOfDay()->lessThanOrEqualTo($asOfDate);
            })
            ->sortByDesc(fn (FALedgerEntry $entry): string => sprintf('%s-%09d', $entry->posting_date?->format('YmdHis') ?? '', $entry->entry_no))
            ->first();

        if ($lastDepreciationEntry?->posting_date !== null) {
            return Carbon::parse($lastDepreciationEntry->posting_date)
                ->addMonthNoOverflow()
                ->endOfMonth();
        }

        if ($asset->depreciation_starting_date !== null) {
            return Carbon::parse($asset->depreciation_starting_date)->endOfMonth();
        }

        return null;
    }

    /**
     * @return array{accumulated_depreciation: float, remaining_book_value: float}
     */
    private function resolveValuation(FixedAsset $asset, ?Carbon $asOfDate): array
    {
        if ($asOfDate === null) {
            return [
                'accumulated_depreciation' => (float) $asset->accumulated_depreciation,
                'remaining_book_value' => (float) $asset->net_book_value,
            ];
        }

        /** @var FALedgerEntry|null $latestEntry */
        $latestEntry = $asset->ledgerEntries
            ->filter(fn (FALedgerEntry $entry): bool => ! $entry->reversed && $entry->posting_date !== null && $entry->posting_date->endOfDay()->lessThanOrEqualTo($asOfDate))
            ->sortByDesc(fn (FALedgerEntry $entry): string => sprintf('%s-%09d', $entry->posting_date?->format('YmdHis') ?? '', $entry->entry_no))
            ->first();

        if ($latestEntry !== null) {
            return [
                'accumulated_depreciation' => (float) $latestEntry->accumulated_depreciation,
                'remaining_book_value' => (float) $latestEntry->book_value_after,
            ];
        }

        if ($asset->acquisition_date !== null && $asset->acquisition_date->endOfDay()->greaterThan($asOfDate)) {
            return [
                'accumulated_depreciation' => 0.0,
                'remaining_book_value' => 0.0,
            ];
        }

        return [
            'accumulated_depreciation' => 0.0,
            'remaining_book_value' => (float) $asset->acquisition_cost,
        ];
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
