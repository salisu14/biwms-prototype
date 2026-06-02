<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\PostedSalesInvoice;
use App\Services\Print\PostedSalesInvoicePrintService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

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
                ->url(CustomerLedgerEntryResource::getUrl('index', [
                    'tableFilters' => [
                        'customer_id' => ['value' => $this->record->customer_id],
                    ],
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
        ];
    }
}
