<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource;
use App\Models\CustomerLedgerEntry;
use App\Models\PaymentApplication;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\SalesShipmentHeader;
use App\Services\Print\PostedSalesInvoicePrintService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ViewPostedSalesInvoice extends Page
{
    protected static string $resource = SalesInvoiceResource::class;

    protected string $view = 'filament.resources.sales-invoices.pages.view-posted-sales-invoice';

    protected static ?string $title = 'Posted Sales Invoice';

    public PostedSalesInvoice $record;

    public static function canAccess(array $parameters = []): bool
    {
        return SalesInvoiceResource::canAccessPostedInvoiceHistory();
    }

    public function mount(PostedSalesInvoice|int|string $record): void
    {
        if ($record instanceof PostedSalesInvoice) {
            $this->record = $record->load(['lines', 'customer', 'salesOrder']);

            return;
        }

        $this->record = PostedSalesInvoice::query()
            ->with(['lines', 'customer', 'salesOrder'])
            ->findOrFail($record);
    }

    public function getHeading(): string
    {
        return 'Posted Sales Invoice '.$this->record->document_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Posted Invoices')
                ->color('gray')
                ->url(SalesInvoiceResource::getUrl('posted')),
            Action::make('viewSubledger')
                ->label('View Subledger')
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url(CustomerSubledgerSummary::getUrl([
                    'customerId' => $this->record->customer_id,
                ])),
            Action::make('printPostedInvoice')
                ->label('Print Posted Invoice')
                ->icon('heroicon-o-document-text')
                ->action(function () {
                    return response()->streamDownload(
                        fn () => print (app(PostedSalesInvoicePrintService::class)->generateTaxInvoice($this->record)->output()),
                        $this->record->document_number.'.pdf'
                    );
                }),
            Action::make('viewRelatedShipment')
                ->label('View Shipment')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->visible(fn (): bool => $this->getRelatedShipmentProperty() !== null)
                ->url(fn (): string => SalesShipmentHeaderResource::getUrl('view', [
                    'record' => $this->getRelatedShipmentProperty(),
                ])),
            Action::make('printWaybill')
                ->label('Print Waybill')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->visible(fn (): bool => $this->getRelatedShipmentProperty() !== null)
                ->url(fn (): string => route('waybill.print', $this->getRelatedShipmentProperty()))
                ->openUrlInNewTab(),
        ];
    }

    public function getRelatedShipmentProperty(): ?SalesShipmentHeader
    {
        if (blank($this->record->order_number)) {
            return null;
        }

        return SalesShipmentHeader::query()
            ->where('order_no', $this->record->order_number)
            ->latest('posting_date')
            ->latest('id')
            ->first();
    }

    public function getApplicationsProperty(): Collection
    {
        $paymentApplications = PaymentApplication::query()
            ->with(['payment'])
            ->active()
            ->forDocument('SALES_INVOICE', $this->record->id)
            ->get()
            ->map(function (PaymentApplication $application): array {
                return [
                    'applied_at' => $application->applied_at,
                    'source_type' => 'Payment',
                    'source_document' => $application->payment?->payment_number ?: $application->document_number,
                    'reference' => $application->payment?->external_reference ?: $application->payment?->memo,
                    'amount' => (float) $application->amount_applied,
                    'balance_after' => (float) $application->document_remaining_after,
                    'source_url' => $application->payment
                        ? PaymentResource::getUrl('view', ['record' => $application->payment])
                        : null,
                ];
            });

        $creditMemoApplications = CustomerLedgerEntry::query()
            ->where('customer_id', $this->record->customer_id)
            ->where('document_type', 'CREDIT_MEMO_APPLICATION')
            ->where('description', 'like', '%'.$this->record->document_number.'%')
            ->get()
            ->map(function (CustomerLedgerEntry $entry): array {
                $creditMemo = PostedSalesCreditMemo::query()
                    ->where('document_number', $entry->document_number)
                    ->first();

                return [
                    'applied_at' => $entry->posting_date,
                    'source_type' => 'Credit Memo',
                    'source_document' => $entry->document_number,
                    'reference' => $entry->description,
                    'amount' => (float) $entry->credit_amount,
                    'balance_after' => null,
                    'source_url' => $creditMemo
                        ? SalesInvoiceResource::getUrl('view-posted-credit-memo', ['record' => $creditMemo])
                        : null,
                ];
            });

        return $paymentApplications
            ->concat($creditMemoApplications)
            ->sortByDesc(fn (array $application) => optional($application['applied_at'])->timestamp ?? 0)
            ->values();
    }
}
