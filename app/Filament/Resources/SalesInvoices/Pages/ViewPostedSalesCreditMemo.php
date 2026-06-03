<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\CustomerLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ViewPostedSalesCreditMemo extends Page
{
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
            $this->record = $record->load(['lines', 'customer', 'correctedInvoice']);

            return;
        }

        $this->record = PostedSalesCreditMemo::query()
            ->with(['lines', 'customer', 'correctedInvoice'])
            ->findOrFail($record);
    }

    public function getHeading(): string
    {
        return 'Posted Sales Credit Memo '.$this->record->document_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Posted Invoices')
                ->color('gray')
                ->url(SalesInvoiceResource::getUrl('posted')),
        ];
    }

    public function getApplicationsProperty(): Collection
    {
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
}
