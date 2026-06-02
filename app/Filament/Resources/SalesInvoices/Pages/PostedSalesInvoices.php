<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\PostedSalesInvoice;
use App\Services\Print\PostedSalesInvoicePrintService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostedSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    protected static ?string $title = 'Posted Sales Invoices';

    protected static ?string $navigationLabel = 'Posted Invoices';

    public static function canAccess(array $parameters = []): bool
    {
        return SalesInvoiceResource::canAccessPostedInvoiceHistory();
    }

    // This filters to only posted invoices
    protected function getTableQuery(): Builder
    {
        return PostedSalesInvoice::query()
            ->whereNotNull('posted_at')
            ->latest('posted_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Amount')
                    ->money(fn (PostedSalesInvoice $record) => $record->currency_code ?: 'NGN')
                    ->sortable(),

                TextColumn::make('posted_at')
                    ->label('Posted Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->state(fn (PostedSalesInvoice $record): string => $record->status),
                // color is provided by the model's computed status text
            ])
            ->filters([
                // Add filters if needed
            ])
            ->recordUrl(fn (PostedSalesInvoice $record): string => SalesInvoiceResource::getUrl('view-posted', [
                'record' => $record->id,
            ]))
            ->recordActions([
                Action::make('viewPosted')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (PostedSalesInvoice $record): string => SalesInvoiceResource::getUrl('view-posted', [
                        'record' => $record->id,
                    ])),
                Action::make('viewSubledger')
                    ->label('View Subledger')
                    ->icon('heroicon-o-book-open')
                    ->color('gray')
                    ->url(fn (PostedSalesInvoice $record) => CustomerSubledgerSummary::getUrl([
                        'customerId' => $record->customer_id,
                    ])),
                Action::make('printPostedInvoice')
                    ->label('Print Posted Invoice')
                    ->icon('heroicon-o-document-text')
                    ->action(function ($record) {
                        $postedInvoice = PostedSalesInvoice::query()
                            ->where('id', $record->id)
                            ->latest('id')
                            ->first();

                        if (! $postedInvoice) {
                            return null;
                        }

                        return response()->streamDownload(
                            fn () => print (app(PostedSalesInvoicePrintService::class)->generateTaxInvoice($postedInvoice)->output()),
                            $postedInvoice->document_number.'.pdf'
                        );
                    }),
            ])
            ->toolbarActions([
                // No bulk delete for posted invoices
            ]);
    }

    // Remove create button
    protected function getHeaderActions(): array
    {
        return [
            // No CreateAction here
        ];
    }
}
