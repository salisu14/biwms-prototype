<?php

namespace App\Filament\Resources\SalesInvoices\Tables;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\PostedSalesInvoice;
use App\Services\Print\PostedSalesInvoicePrintService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PostedSalesInvoicesTable
{
    public static function configure(Table $table): Table
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
                    ->sortable()
                    ->description(fn (PostedSalesInvoice $record): string => $record->customer?->customer_number ?? ''),

                TextColumn::make('grand_total')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, PostedSalesInvoice $record): string => Number::currency((float) $state, $record->currency_code ?: config('app.default_currency', 'USD')))
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->formatStateUsing(fn ($state, PostedSalesInvoice $record): string => Number::currency((float) $state, $record->currency_code ?: config('app.default_currency', 'USD')))
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('posted_at')
                    ->label('Posted Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->state(fn (PostedSalesInvoice $record): string => str_replace('_', ' ', $record->status))
                    ->color(fn (PostedSalesInvoice $record): string => match ($record->status) {
                        'PAID' => 'success',
                        'OVERDUE' => 'danger',
                        'CANCELLED' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->recordUrl(fn (PostedSalesInvoice $record): string => SalesInvoiceResource::getUrl('view-posted', [
                'record' => $record->id,
            ]))
            ->recordActions([
                Action::make('viewPosted')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (PostedSalesInvoice $record): bool => auth()->user()?->can('view', $record) === true)
                    ->url(fn (PostedSalesInvoice $record): string => SalesInvoiceResource::getUrl('view-posted', [
                        'record' => $record->id,
                    ])),

                Action::make('printPostedInvoice')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->visible(fn (PostedSalesInvoice $record): bool => auth()->user()?->can('print', $record) === true)
                    ->action(fn (PostedSalesInvoice $record) => static::downloadPdf($record)),

                Action::make('downloadPostedInvoice')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (PostedSalesInvoice $record): bool => auth()->user()?->can('print', $record) === true)
                    ->action(fn (PostedSalesInvoice $record) => static::downloadPdf($record)),

                Action::make('exportPostedInvoice')
                    ->label('Export')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (PostedSalesInvoice $record): bool => auth()->user()?->can('export', $record) === true)
                    ->action(fn (PostedSalesInvoice $record) => response()->streamDownload(function () use ($record): void {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Document No.', 'Customer', 'Posting Date', 'Currency', 'Grand Total', 'Remaining Amount']);
                        fputcsv($handle, [
                            $record->document_number,
                            $record->customer_name,
                            optional($record->posting_date)->toDateString(),
                            $record->currency_code,
                            (float) $record->grand_total,
                            (float) $record->remaining_amount,
                        ]);
                        fclose($handle);
                    }, $record->document_number.'.csv')),

                Action::make('viewSubledger')
                    ->label('Subledger')
                    ->icon('heroicon-o-book-open')
                    ->color('gray')
                    ->visible(fn (PostedSalesInvoice $record): bool => auth()->user()?->can('view', $record) === true)
                    ->url(fn (PostedSalesInvoice $record): string => CustomerSubledgerSummary::getUrl([
                        'customerId' => $record->customer_id,
                    ])),
            ])
            ->toolbarActions([]);
    }

    private static function downloadPdf(PostedSalesInvoice $record)
    {
        return response()->streamDownload(
            fn () => print (app(PostedSalesInvoicePrintService::class)->generateTaxInvoice($record)->output()),
            $record->document_number.'.pdf'
        );
    }
}
