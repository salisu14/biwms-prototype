<?php

declare(strict_types=1);

namespace App\Filament\Pages\Finance;

use App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource;
use App\Services\Inventory\ItemLedgerSummaryService;
use Filament\Pages\Page;

class ItemLedgerSummary extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $title = 'Item Ledger Summary';

    protected static ?string $slug = 'item-ledger-summary';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.finance.item-ledger-summary';

    public ?string $entryTypeFilter = null;

    public ?string $monthFilter = null;

    public ?int $itemId = null;

    public ?int $locationId = null;

    public string $printUrl = '';

    public string $csvUrl = '';

    public function mount(): void
    {
        $this->entryTypeFilter = filled(request()->query('entryTypeFilter'))
            ? (string) request()->query('entryTypeFilter')
            : null;
        $this->monthFilter = filled(request()->query('monthFilter'))
            ? (string) request()->query('monthFilter')
            : null;
        $this->itemId = request()->integer('itemId') ?: null;
        $this->locationId = request()->integer('locationId') ?: null;

        $query = array_filter([
            'entryTypeFilter' => $this->entryTypeFilter,
            'monthFilter' => $this->monthFilter,
            'itemId' => $this->itemId,
            'locationId' => $this->locationId,
        ], fn ($value) => filled($value));

        $this->printUrl = route('reports.item-ledger-summary.print', $query);
        $this->csvUrl = route('reports.item-ledger-summary.print', [...$query, 'format' => 'csv']);
    }

    public function getViewData(): array
    {
        $report = app(ItemLedgerSummaryService::class)->generate([
            'entry_type' => $this->entryTypeFilter,
            'month_filter' => $this->monthFilter,
            'item_id' => $this->itemId,
            'location_id' => $this->locationId,
        ]);

        return [
            ...$report,
            'activeFilterCount' => collect([$this->entryTypeFilter, $this->monthFilter, $this->itemId, $this->locationId])->filter()->count(),
            'entryTypeFilter' => $this->entryTypeFilter,
            'monthFilter' => $this->monthFilter,
            'itemId' => $this->itemId,
            'locationId' => $this->locationId,
            'printUrl' => $this->printUrl,
            'csvUrl' => $this->csvUrl,
            'detailUrl' => ItemLedgerEntryResource::getUrl('index', [
                'tableFilters' => array_filter([
                    'entry_type' => $this->entryTypeFilter !== null ? ['value' => $this->entryTypeFilter] : null,
                    'item_id' => $this->itemId !== null ? ['value' => $this->itemId] : null,
                    'location_id' => $this->locationId !== null ? ['value' => $this->locationId] : null,
                ]),
            ]),
        ];
    }
}
