<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PostedPurchaseInvoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostedPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected static ?string $title = 'Posted Purchase Invoices';

    protected static ?string $navigationLabel = 'Posted Purchase Invoices';

    protected function getTableQuery(): Builder
    {
        return PostedPurchaseInvoice::query()
            ->whereNotNull('posted_at')
            ->latest('posted_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (PostedPurchaseInvoice $record): string => PurchaseInvoiceResource::getUrl('view-posted', [
                'record' => $record,
            ]))
            ->columns([
                TextColumn::make('document_number')->label('Invoice No.')->searchable()->sortable(),
                TextColumn::make('vendor_name')->label('Vendor')->searchable()->sortable(),
                TextColumn::make('grand_total')
                    ->label('Amount')
                    ->money(fn (PostedPurchaseInvoice $record) => $record->currency_code ?: 'USD')
                    ->sortable(),
                TextColumn::make('posted_at')->label('Posted Date')->dateTime()->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (PostedPurchaseInvoice $record): string => PurchaseInvoiceResource::getUrl('view-posted', [
                        'record' => $record,
                    ])),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
