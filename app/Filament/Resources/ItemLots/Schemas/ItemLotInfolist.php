<?php

namespace App\Filament\Resources\ItemLots\Schemas;

use App\Filament\Resources\Items\ItemResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemLotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lot_number')
                            ->label('Lot No')
                            ->weight('bold'),

                        TextEntry::make('item.item_code')
                            ->label('Item')
                            ->formatStateUsing(fn ($state, $record): string => $record->item
                                ? "{$record->item->item_code} - {$record->item->description}"
                                : '—')
                            ->url(fn ($record): ?string => $record->item
                                ? ItemResource::getUrl('view', ['record' => $record->item])
                                : null),

                        TextEntry::make('supplier_lot')
                            ->label('Supplier Lot')
                            ->placeholder('-'),

                        TextEntry::make('coa_reference')
                            ->label('COA Reference')
                            ->placeholder('-'),
                    ]),

                Section::make('Lot Control')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('receipt_date')
                            ->date(),
                        TextEntry::make('expiry_date')
                            ->date(),
                        TextEntry::make('retest_date')
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->badge(),
                    ]),

                Section::make('Inventory')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('quantity_received')
                            ->numeric(),
                        TextEntry::make('quantity_remaining')
                            ->numeric(),
                    ]),

                Section::make('Metadata')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->collapsible(),
            ]);
    }
}
