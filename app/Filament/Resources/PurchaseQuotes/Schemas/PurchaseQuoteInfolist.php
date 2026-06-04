<?php

namespace App\Filament\Resources\PurchaseQuotes\Schemas;

use App\Filament\Resources\Vendors\VendorResource;
use App\Models\PurchaseQuote;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;

class PurchaseQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Split::make([
                    Group::make([
                        Section::make('Scope')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('document_no')->weight('bold'),
                                    TextEntry::make('status')->badge(),
                                    TextEntry::make('vendor_label')
                                        ->label('Vendor')
                                        ->state(fn (PurchaseQuote $record): string => $record->vendor
                                            ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
                                            : '—')
                                        ->url(fn (PurchaseQuote $record): ?string => $record->vendor
                                            ? VendorResource::getUrl('view', ['record' => $record->vendor])
                                            : null),
                                    TextEntry::make('buyer_label')
                                        ->label('Buyer')
                                        ->state(fn (PurchaseQuote $record): string => $record->buyer?->name ?? '—'),
                                ]),
                            ]),
                        Section::make('Notes')
                            ->schema([
                                TextEntry::make('vendor_note')->markdown(),
                                TextEntry::make('internal_note')->markdown(),
                            ]),
                    ])->grow(),

                    Group::make([
                        Section::make('Financial Summary')
                            ->schema([
                                TextEntry::make('amount')
                                    ->money(fn ($record) => $record->currency_code),
                                TextEntry::make('vat_amount')
                                    ->money(fn ($record) => $record->currency_code),
                                TextEntry::make('amount_including_vat')
                                    ->label('Total')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->money(fn ($record) => $record->currency_code),
                            ]),
                        Section::make('Timeline')
                            ->schema([
                                TextEntry::make('document_date')->date(),
                                TextEntry::make('posting_date')->date(),
                                TextEntry::make('due_date')->date(),
                                TextEntry::make('requested_receipt_date')->date(),
                                TextEntry::make('promised_receipt_date')->date(),
                            ]),
                    ])->columnSpan(1),
                ])->columnSpanFull(),
            ]);
    }
}
