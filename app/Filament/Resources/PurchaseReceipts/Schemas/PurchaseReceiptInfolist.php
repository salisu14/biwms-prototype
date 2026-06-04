<?php

namespace App\Filament\Resources\PurchaseReceipts\Schemas;

use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\PurchaseReceipt;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseReceiptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Group::make([
                            Section::make('Scope')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('document_number')
                                            ->label('Receipt No.')
                                            ->weight('bold'),
                                        TextEntry::make('status')
                                            ->badge(),
                                        TextEntry::make('buy_from_vendor_name')
                                            ->label('Vendor')
                                            ->state(fn (PurchaseReceipt $record): string => $record->vendor?->vendor_code
                                                ? "{$record->vendor->vendor_code} - ".($record->vendor?->vendor_name ?? $record->buy_from_vendor_name ?? '—')
                                                : ($record->buy_from_vendor_name ?? '—'))
                                            ->url(fn (PurchaseReceipt $record): ?string => $record->vendor
                                                ? VendorResource::getUrl('view', ['record' => $record->vendor])
                                                : null),
                                        TextEntry::make('purchase_order_link')
                                            ->label('Purchase Order')
                                            ->state(fn (PurchaseReceipt $record): string => $record->purchaseOrder
                                                ? "{$record->purchaseOrder->order_number} - ".($record->purchaseOrder->vendor_name ?? 'Unknown Vendor')
                                                : ($record->purchase_order_no ?? '—'))
                                            ->url(fn (PurchaseReceipt $record): ?string => $record->purchaseOrder
                                                ? PurchaseOrderResource::getUrl('view', ['record' => $record->purchaseOrder])
                                                : null),
                                    ]),
                                ]),

                            Section::make('Receipt Details')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextEntry::make('vendor_shipment_no')
                                            ->label('Vendor Shipment No.')
                                            ->placeholder('—'),
                                        TextEntry::make('vendor_invoice_no')
                                            ->label('Vendor Invoice No.')
                                            ->placeholder('—'),
                                        TextEntry::make('external_document_no')
                                            ->label('Reference No.')
                                            ->placeholder('—'),
                                        TextEntry::make('receivingLocation.name')
                                            ->label('Receiving Location'),
                                        TextEntry::make('location_link')
                                            ->label('Location')
                                            ->state(fn (PurchaseReceipt $record): string => $record->receivingLocation?->code
                                                ? "{$record->receivingLocation->code} - {$record->receivingLocation->name}"
                                                : ($record->receivingLocation?->name ?? $record->location_code ?? '—'))
                                            ->url(fn (PurchaseReceipt $record): ?string => $record->receivingLocation
                                                ? LocationResource::getUrl('view', ['record' => $record->receivingLocation])
                                                : null),
                                        TextEntry::make('shipment_method_code')
                                            ->label('Shipment Method')
                                            ->placeholder('—'),
                                        TextEntry::make('shipping_agent_code')
                                            ->label('Shipping Agent')
                                            ->placeholder('—'),
                                    ]),
                                ]),

                            Section::make('Addresses')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('buy_from_address')
                                            ->label('Buy-from Address')
                                            ->placeholder('—'),
                                        TextEntry::make('ship_to_name')
                                            ->label('Ship-to Name')
                                            ->placeholder('—'),
                                        TextEntry::make('buy_from_city')
                                            ->label('Buy-from City')
                                            ->placeholder('—'),
                                        TextEntry::make('ship_to_city')
                                            ->label('Ship-to City')
                                            ->placeholder('—'),
                                    ]),
                                ]),
                        ])->columnSpan(2),

                        Group::make([
                            Section::make('Timeline')
                                ->schema([
                                    TextEntry::make('posting_date')->date(),
                                    TextEntry::make('document_date')->date(),
                                    TextEntry::make('expected_receipt_date')->date(),
                                    TextEntry::make('actual_receipt_date')->date(),
                                ]),
                            Section::make('Posting')
                                ->schema([
                                    IconEntry::make('posted')->boolean(),
                                    TextEntry::make('postedByUser.name')
                                        ->label('Posted By')
                                        ->placeholder('—'),
                                    TextEntry::make('posted_at')
                                        ->dateTime()
                                        ->placeholder('—'),
                                ]),
                        ])->columnSpan(1),
                    ]),
            ]);
    }
}
