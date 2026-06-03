<?php

declare(strict_types=1);

namespace App\Filament\Pages\Finance;

use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Payment;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Services\Customer\CustomerSubledgerSummaryService;
use Filament\Pages\Page;

class CustomerSubledgerSummary extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $title = 'Customer Subledger Summary';

    protected static ?string $slug = 'customer-subledger-summary';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.finance.customer-subledger-summary';

    public ?int $customerId = null;

    public ?string $documentTypeFilter = null;

    public ?string $monthFilter = null;

    public ?Customer $customer = null;

    public string $printUrl = '';

    public string $csvUrl = '';

    public function mount(): void
    {
        $this->customerId = request()->integer('customerId') ?: null;
        $this->documentTypeFilter = filled(request()->query('documentTypeFilter'))
            ? (string) request()->query('documentTypeFilter')
            : null;
        $this->monthFilter = filled(request()->query('monthFilter'))
            ? (string) request()->query('monthFilter')
            : null;

        $this->customer = $this->customerId !== null
            ? Customer::query()->find($this->customerId)
            : null;

        $query = array_filter([
            'customerId' => $this->customerId,
            'documentTypeFilter' => $this->documentTypeFilter,
            'monthFilter' => $this->monthFilter,
        ], fn ($value) => filled($value));

        $this->printUrl = route('reports.customer-subledger-summary.print', $query);
        $this->csvUrl = route('reports.customer-subledger-summary.print', [...$query, 'format' => 'csv']);
    }

    public function getViewData(): array
    {
        $report = app(CustomerSubledgerSummaryService::class)->generate([
            'customer_id' => $this->customerId,
            'document_type' => $this->documentTypeFilter,
            'month_filter' => $this->monthFilter,
        ]);

        return [
            ...$report,
            'customer' => $this->customer,
            'activeFilterCount' => collect([$this->documentTypeFilter, $this->monthFilter])->filter()->count(),
            'documentTypeFilter' => $this->documentTypeFilter,
            'monthFilter' => $this->monthFilter,
            'printUrl' => $this->printUrl,
            'csvUrl' => $this->csvUrl,
            'detailUrl' => CustomerLedgerEntryResource::getUrl('index', [
                'customerId' => $this->customerId,
                'tableFilters' => array_filter([
                    'customer_id' => $this->customerId !== null ? ['value' => $this->customerId] : null,
                    'document_type' => $this->documentTypeFilter !== null ? ['value' => $this->documentTypeFilter] : null,
                ]),
            ]),
        ];
    }

    public function resolveEntrySourceUrl(CustomerLedgerEntry $entry): ?string
    {
        if ($entry->source_type === PostedSalesInvoice::class && $entry->source_id) {
            return SalesInvoiceResource::getUrl('view-posted', ['record' => $entry->source_id]);
        }

        if ($entry->source_type === PostedSalesCreditMemo::class && $entry->source_id) {
            return SalesInvoiceResource::getUrl('view-posted-credit-memo', ['record' => $entry->source_id]);
        }

        if ($entry->source_type === Payment::class && $entry->source_id) {
            return PaymentResource::getUrl('view', ['record' => $entry->source_id]);
        }

        return null;
    }
}
