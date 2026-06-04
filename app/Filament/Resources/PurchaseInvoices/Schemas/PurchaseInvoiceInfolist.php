<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\PurchaseInvoice;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class PurchaseInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Group::make([
                            Section::make('Scope')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('document_number')
                                            ->label('Invoice No.')
                                            ->weight('bold'),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->label('Approval'),
                                        TextEntry::make('vendor.vendor_name')
                                            ->label('Vendor')
                                            ->state(fn (PurchaseInvoice $record): string => $record->vendor_name ?: ($record->vendor?->vendor_name ?? '—'))
                                            ->url(fn (PurchaseInvoice $record): ?string => $record->vendor
                                                ? VendorResource::getUrl('view', ['record' => $record->vendor])
                                                : null),
                                        TextEntry::make('purchaseOrder.document_number')
                                            ->label('Purchase Order')
                                            ->state(fn (PurchaseInvoice $record): string => $record->order_number ?: '—')
                                            ->url(fn (PurchaseInvoice $record): ?string => $record->purchaseOrder
                                                ? PurchaseOrderResource::getUrl('view', ['record' => $record->purchaseOrder])
                                                : null),
                                    ]),
                                ]),

                            Section::make('Addresses')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('vendor_address')->label('Vendor Address')->placeholder('—'),
                                        TextEntry::make('location.name')
                                            ->label('Location')
                                            ->url(fn (PurchaseInvoice $record): ?string => $record->location
                                                ? LocationResource::getUrl('view', ['record' => $record->location])
                                                : null),
                                        TextEntry::make('external_document_number')
                                            ->label('External Doc No.')
                                            ->placeholder('—'),
                                        TextEntry::make('payment_status')
                                            ->badge()
                                            ->label('Payment Status')
                                            ->color(fn (string $state): string => match ($state) {
                                                'PAID' => 'success',
                                                'OVERDUE' => 'danger',
                                                'CANCELLED' => 'gray',
                                                default => 'warning',
                                            }),
                                    ]),
                                ]),

                            Section::make('Cancellation Details')
                                ->visible(fn (PurchaseInvoice $record) => $record->cancelled)
                                ->schema([
                                    TextEntry::make('corrective_document_number')
                                        ->label('Credit Memo Ref')
                                        ->weight('bold')
                                        ->color('danger'),
                                    TextEntry::make('cancellation_reason')
                                        ->placeholder('No reason provided'),
                                    TextEntry::make('cancelled_at')
                                        ->dateTime(),
                                ]),
                        ])->grow(),

                        Group::make([
                            Section::make('Financial Summary')
                                ->schema([
                                    TextEntry::make('grand_total')
                                        ->label('Total (Incl. VAT)')
                                        ->state(fn (PurchaseInvoice $record): string => Number::currency((float) $record->grand_total, $record->currency_code ?: config('app.default_currency', 'USD')))
                                        ->size('lg')
                                        ->weight('bold'),
                                    TextEntry::make('total_vat')
                                        ->label('VAT Amount')
                                        ->state(fn (PurchaseInvoice $record): string => Number::currency((float) $record->total_vat, $record->currency_code ?: config('app.default_currency', 'USD'))),
                                    TextEntry::make('amount_paid')
                                        ->label('Paid to Date')
                                        ->state(fn (PurchaseInvoice $record): string => Number::currency((float) $record->amount_paid, $record->currency_code ?: config('app.default_currency', 'USD')))
                                        ->color('success'),
                                    TextEntry::make('remaining_amount')
                                        ->label('Balance Due')
                                        ->state(fn (PurchaseInvoice $record): string => Number::currency((float) $record->remaining_amount, $record->currency_code ?: config('app.default_currency', 'USD')))
                                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                                        ->weight('bold'),
                                ]),

                            Section::make('Timeline')
                                ->schema([
                                    TextEntry::make('posting_date')->date(),
                                    TextEntry::make('document_date')->date(),
                                    TextEntry::make('due_date')
                                        ->date()
                                        ->color(fn (PurchaseInvoice $record) => $record->is_overdue ? 'danger' : null),
                                    TextEntry::make('posted_at')->dateTime(),
                                ]),

                            Section::make('Audit')
                                ->schema([
                                    IconEntry::make('paid_in_full')
                                        ->label('Paid')
                                        ->boolean(),
                                    IconEntry::make('cancelled')
                                        ->label('Cancelled')
                                        ->boolean()
                                        ->trueColor('danger'),
                                    TextEntry::make('poster.name')
                                        ->label('Posted By'),
                                ]),
                        ])->columnSpan(1),
                    ]),
            ]);
    }
}
