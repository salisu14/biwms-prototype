<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseInvoice;
use App\Services\Print\PostedPurchaseInvoicePrintService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ViewPostedPurchaseInvoice extends Page
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected string $view = 'filament.resources.purchase-invoices.pages.view-posted-purchase-invoice';

    protected static ?string $title = 'Posted Purchase Invoice';

    public PostedPurchaseInvoice $record;

    public function mount(PostedPurchaseInvoice|int|string $record): void
    {
        if ($record instanceof PostedPurchaseInvoice) {
            $this->record = $record->load(['lines', 'vendor', 'purchaseOrder']);

            return;
        }

        $this->record = PostedPurchaseInvoice::query()
            ->with(['lines', 'vendor', 'purchaseOrder'])
            ->findOrFail($record);
    }

    public function getHeading(): string
    {
        return ($this->record->document_number ?? 'Posted Purchase Invoice')
            .' • Scope '.($this->record->vendor_name ?? '—')
            .' • Attribute '.number_format((float) $this->record->grand_total, 2);
    }

    public function getSubheading(): string
    {
        return ($this->record->order_number ?? 'No purchase order')
            .' • '.($this->record->vendor?->vendor_name ?? $this->record->vendor_name ?? 'Unknown Vendor')
            .' • Posted '.optional($this->record->posted_at)->format('d/m/Y H:i');
    }

    public function getBreadcrumb(): string
    {
        return $this->record->document_number ?? 'Posted Purchase Invoice';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Posted Purchase Invoices')
                ->color('gray')
                ->url(PurchaseInvoiceResource::getUrl('posted')),
            Action::make('printPostedPurchaseInvoice')
                ->label('Print Posted Invoice')
                ->icon('heroicon-o-document-text')
                ->action(function () {
                    return response()->streamDownload(
                        fn () => print (app(PostedPurchaseInvoicePrintService::class)->generatePurchaseInvoice($this->record)->output()),
                        $this->record->document_number.'.pdf'
                    );
                }),
        ];
    }

    public function getPaymentApplicationsProperty(): Collection
    {
        return PaymentApplication::query()
            ->with(['payment'])
            ->active()
            ->forDocument('PURCHASE_INVOICE', $this->record->id)
            ->latest('applied_at')
            ->get()
            ->map(function (PaymentApplication $application): array {
                return [
                    'applied_at' => $application->applied_at,
                    'source_type' => 'Payment',
                    'status' => 'Payment Applied',
                    'status_color' => 'emerald',
                    'source_document' => $application->payment?->payment_number,
                    'payment_number' => $application->payment?->payment_number,
                    'reference' => $application->payment?->external_reference ?: $application->payment?->memo,
                    'amount_applied' => (float) $application->amount_applied,
                    'document_remaining_after' => (float) $application->document_remaining_after,
                    'source_url' => $application->payment
                        ? PaymentResource::getUrl('view', ['record' => $application->payment])
                        : null,
                ];
            });
    }
}
