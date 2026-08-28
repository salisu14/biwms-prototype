<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Pages\Finance\CustomerSettlementHistory;
use App\Filament\Resources\SalesCreditMemos\Concerns\InteractsWithSalesCreditMemoApplications;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\CustomerLedgerApplication;
use App\Models\CustomerLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class ViewPostedSalesCreditMemo extends Page
{
    use InteractsWithSalesCreditMemoApplications;

    protected static string $resource = SalesInvoiceResource::class;

    protected string $view = 'filament.resources.sales-invoices.pages.view-posted-sales-credit-memo';

    protected static ?string $title = 'Posted Sales Credit Memo';

    public PostedSalesCreditMemo $record;

    public static function canAccess(array $parameters = []): bool
    {
        return SalesInvoiceResource::canAccessPostedInvoiceHistory();
    }

    public function mount(PostedSalesCreditMemo|int|string $record): void
    {
        if ($record instanceof PostedSalesCreditMemo) {
            $this->record = $record->load(['lines', 'customer', 'correctedInvoice', 'salesOrder', 'location']);

            return;
        }

        $this->record = PostedSalesCreditMemo::query()
            ->with(['lines', 'customer', 'correctedInvoice', 'salesOrder', 'location'])
            ->findOrFail($record);
    }

    public function getHeading(): string
    {
        $customer = $this->record->customer?->customer_name ?? $this->record->customer?->name ?? 'Unknown Customer';
        $amount = Number::currency((float) $this->record->grand_total, $this->record->currency_code ?: config('app.default_currency', 'USD'));

        return ($this->record->document_number ?? 'Posted Sales Credit Memo')
            .' • '.$customer
            .' • '.$amount;
    }

    public function getSubheading(): string
    {
        $location = $this->record->location?->code
            ? "{$this->record->location->code} - {$this->record->location->name}"
            : ($this->record->location?->name ?? 'Unknown Location');

        return trim(implode(' • ', array_filter([
            $this->record->correctedInvoice?->invoice_number ?: 'No corrected invoice',
            $location,
            'Posted '.optional($this->record->posting_date)->format('d/m/Y'),
        ])));
    }

    public function getBreadcrumb(): string
    {
        $customer = $this->record->customer?->customer_name ?? $this->record->customer?->name ?? 'Unknown Customer';

        return ($this->record->document_number ?? 'Posted Sales Credit Memo').' - '.$customer;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->applyCreditMemoAction(),
            Action::make('viewSettlementHistory')
                ->label('Settlement History')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('finance.customer_settlement_history.view') === true)
                ->url(CustomerSettlementHistory::getUrl(panel: 'finance', parameters: [
                    'customer_id' => $this->record->customer_id,
                    'source_document_number' => $this->record->document_number,
                ])),
            Action::make('back')
                ->label('Back to Posted Invoices')
                ->color('gray')
                ->url(SalesInvoiceResource::getUrl('posted')),
        ];
    }

    public function getApplicationsProperty(): Collection
    {
        $ledgerApplications = CustomerLedgerApplication::query()
            ->with(['targetInvoice', 'applier'])
            ->where('source_posted_sales_credit_memo_id', $this->record->id)
            ->where('reversed', false)
            ->orderByDesc('applied_at')
            ->get()
            ->map(fn (CustomerLedgerApplication $application): array => [
                'entry_id' => $application->target_customer_ledger_entry_id,
                'document_number' => $application->targetInvoice?->document_number,
                'amount' => (float) $application->amount,
                'applied_at' => optional($application->applied_at)->toDateTimeString(),
                'applied_by' => $application->applier?->name,
                'invoice_record_id' => $application->target_posted_sales_invoice_id,
                'source_remaining_before' => (float) $application->source_remaining_before,
                'source_remaining_after' => (float) $application->source_remaining_after,
                'target_remaining_before' => (float) $application->target_remaining_before,
                'target_remaining_after' => (float) $application->target_remaining_after,
                'currency_code' => $application->currency_code,
                'application_reference' => $application->idempotency_key ? substr($application->idempotency_key, 0, 12) : null,
                'trace_type' => CustomerLedgerApplication::class,
            ]);

        if ($ledgerApplications->isNotEmpty()) {
            return $ledgerApplications->values();
        }

        $creditMemoEntry = CustomerLedgerEntry::query()
            ->where('source_type', PostedSalesCreditMemo::class)
            ->where('source_id', $this->record->id)
            ->first();

        return collect($creditMemoEntry?->applied_to_entries ?? [])
            ->map(function (array $application): array {
                $invoiceLedgerEntry = CustomerLedgerEntry::query()->find($application['entry_id'] ?? null);
                $invoiceRecordId = null;

                if ($invoiceLedgerEntry?->source_type === PostedSalesInvoice::class) {
                    $invoiceRecordId = $invoiceLedgerEntry->source_id;
                }

                return [
                    ...$application,
                    'invoice_record_id' => $invoiceRecordId,
                ];
            })
            ->values();
    }

    protected function applicationPostedSalesCreditMemo(): ?PostedSalesCreditMemo
    {
        return $this->record;
    }

    protected function refreshAfterCreditMemoApplication(?PostedSalesCreditMemo $postedCreditMemo): void
    {
        if ($postedCreditMemo) {
            $this->record = $postedCreditMemo->load(['lines', 'customer', 'correctedInvoice', 'salesOrder', 'location']);
        }
    }
}
