<?php

namespace App\Filament\Resources\ItemLedgers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemLedgerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('item.item_code')
                            ->label('Item')
                            ->helperText(fn ($record) => $record->item->description ?? '-'),

                        TextEntry::make('location.location_name')
                            ->label('Location')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('entry_type')
                            ->label('Entry Type')
                            ->badge()
                            ->color(fn ($record): string => $record->is_inbound ? 'success' : 'danger'),

                        // FIX: use correct column name (doc_no instead of doc_number)
                        TextEntry::make('document.doc_no')
                            ->label('Reference Document')
                            ->placeholder('-'),

                        TextEntry::make('created_at')
                            ->label('Transaction Date')
                            ->dateTime(),

                        TextEntry::make('creator.name')
                            ->label('Entered By'),
                    ]),

                Section::make('Movement & Cost')
                    ->description('Quantities and values associated with this entry.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('signed_quantity')
                            ->label('Quantity')
                            ->formatStateUsing(fn ($record): string =>
                                ($record->is_inbound ? '+' : '-') . number_format($record->quantity, 4)
                            )
                            ->suffix(fn ($record): string => ' ' . ($record->uom->uom_code ?? ''))
                            ->weight('bold')
                            ->color(fn ($record): string => $record->is_inbound ? 'success' : 'danger'),

                        TextEntry::make('unit_cost')
                            ->label('Unit Cost')
                            ->money('USD'),

                        TextEntry::make('cost_amount')
                            ->label('Total Value')
                            ->money('USD')
                            ->color('primary'),

                        Grid::make(2)->schema([
                            TextEntry::make('balance_after')
                                ->label('Balance After')
                                ->numeric()
                                ->suffix(' qty'),

                            TextEntry::make('cost_after')
                                ->label('Inv. Value After')
                                ->money('USD'),
                        ]),
                    ]),

                Section::make('Trackability')
                    ->description('Lot and expiry information.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lot_number')
                            ->label('Lot Number')
                            ->placeholder('-'),

                        TextEntry::make('expiry_date')
                            ->label('Expiry Date')
                            ->date()
                            ->placeholder('N/A'),
                    ])
                    ->collapsible(),
            ]);
    }
}
