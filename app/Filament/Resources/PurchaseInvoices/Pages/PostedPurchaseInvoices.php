<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PostedPurchaseInvoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class PostedPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected static ?string $title = 'Posted Purchase Invoices';

    protected static ?string $navigationLabel = 'Posted Purchase Invoices';

    protected function getTableQuery(): Builder
    {
        return PostedPurchaseInvoice::query()
            ->with(['vendor', 'purchaseOrder', 'location'])
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
                TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PostedPurchaseInvoice $record): string => $record->vendor?->vendor_code ?? ''),
                TextColumn::make('order_number')
                    ->label('Purchase Order')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('grand_total')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, PostedPurchaseInvoice $record): string => Number::currency((float) $state, $record->currency_code ?: config('app.default_currency', 'USD')))
                    ->sortable(),
                TextColumn::make('posted_at')->label('Posted Date')->dateTime()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state))
                    ->color(fn (string $state): string => match ($state) {
                        'PAID' => 'success',
                        'OVERDUE' => 'danger',
                        'CANCELLED' => 'gray',
                        default => 'warning',
                    }),
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
