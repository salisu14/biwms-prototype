<?php

declare(strict_types=1);

namespace App\Filament\Pages\Finance;

use App\Filament\Resources\FixedAssets\FixedAssetResource;
use App\Models\FALedgerEntry;
use App\Models\FixedAsset;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class FixedAssetLedgerEntries extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $title = 'Fixed Asset Ledger Entries';

    protected static ?string $slug = 'fixed-asset-ledger-entries';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.finance.fixed-asset-ledger-entries';

    public ?int $fixedAssetId = null;

    public ?string $asOfDate = null;

    public ?string $typeFilter = null;

    public ?string $monthFilter = null;

    public ?FixedAsset $asset = null;

    public function mount(): void
    {
        $this->fixedAssetId = request()->integer('fixedAssetId') ?: null;
        $this->asOfDate = filled(request()->query('asOfDate'))
            ? Carbon::parse((string) request()->query('asOfDate'))->toDateString()
            : null;
        $this->typeFilter = filled(request()->query('typeFilter'))
            ? (string) request()->query('typeFilter')
            : null;
        $this->monthFilter = filled(request()->query('monthFilter'))
            ? (string) request()->query('monthFilter')
            : null;

        $this->asset = $this->fixedAssetId !== null
            ? FixedAsset::query()->with(['faClass', 'location', 'depreciationBook'])->find($this->fixedAssetId)
            : null;
    }

    public function getViewData(): array
    {
        $entriesQuery = FALedgerEntry::query()
            ->with(['depreciationBook'])
            ->when(
                $this->fixedAssetId !== null,
                fn ($query) => $query->where('fixed_asset_id', $this->fixedAssetId)
            )
            ->when(
                $this->asOfDate !== null,
                fn ($query) => $query->whereDate('posting_date', '<=', $this->asOfDate)
            )
            ->when(
                $this->typeFilter !== null,
                fn ($query) => $query->where('fa_posting_type', $this->typeFilter)
            )
            ->when(
                $this->monthFilter !== null,
                fn ($query) => $query->whereRaw("to_char(posting_date, 'YYYY-MM') = ?", [$this->monthFilter])
            )
            ->orderByDesc('posting_date')
            ->orderByDesc('entry_no');

        $entries = $entriesQuery->get();
        $movementSummary = $entries
            ->groupBy(fn (FALedgerEntry $entry): string => (string) $entry->fa_posting_type)
            ->map(fn ($group, $type): array => [
                'type' => (string) $type,
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
                'depreciation_amount' => (float) $group->sum('depreciation_amount'),
            ])
            ->sortBy('type')
            ->values()
            ->all();

        $dateBuckets = $entries
            ->groupBy(fn (FALedgerEntry $entry): string => optional($entry->posting_date)->format('Y-m') ?? 'unknown')
            ->map(fn ($group, $bucket): array => [
                'bucket' => (string) $bucket,
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
            ])
            ->sortBy('bucket')
            ->values()
            ->all();

        return [
            'asset' => $this->asset,
            'entries' => $entries,
            'asOfDate' => $this->asOfDate,
            'activeFilterCount' => collect([$this->typeFilter, $this->monthFilter])->filter()->count(),
            'summary' => [
                'count' => $entries->count(),
                'amount' => (float) $entries->sum('amount'),
                'depreciation_amount' => (float) $entries->sum('depreciation_amount'),
                'book_value_after' => (float) ($entries->first()?->book_value_after ?? 0),
            ],
            'movementSummary' => $movementSummary,
            'dateBuckets' => $dateBuckets,
            'fixedAssetViewUrl' => $this->asset !== null
                ? FixedAssetResource::getUrl('view', ['record' => $this->asset])
                : null,
            'csvExportUrl' => route('reports.fixed-asset-ledger.export', [
                'fixedAssetId' => $this->fixedAssetId,
                'asOfDate' => $this->asOfDate,
                'typeFilter' => $this->typeFilter,
                'monthFilter' => $this->monthFilter,
                'format' => 'csv',
            ]),
            'printExportUrl' => route('reports.fixed-asset-ledger.export', [
                'fixedAssetId' => $this->fixedAssetId,
                'asOfDate' => $this->asOfDate,
                'typeFilter' => $this->typeFilter,
                'monthFilter' => $this->monthFilter,
            ]),
            'typeFilter' => $this->typeFilter,
            'monthFilter' => $this->monthFilter,
        ];
    }
}
