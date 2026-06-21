<?php

namespace App\Filament\Resources\ItemLots\Schemas;

use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ItemLotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'item_code')
                    ->getOptionLabelFromRecordUsing(fn (Item $record): string => "{$record->item_code} - {$record->description}")
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('lot_number')
                    ->label('Lot No')
                    ->required(),
                TextInput::make('supplier_lot')
                    ->label('Supplier Lot'),
                DatePicker::make('receipt_date')
                    ->required(),
                DatePicker::make('expiry_date')
                    ->required(),
                DatePicker::make('retest_date'),
                TextInput::make('quantity_received')
                    ->required()
                    ->numeric(),
                TextInput::make('quantity_remaining')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('status')
                    ->required()
                    ->default('QUARANTINE'),
                TextInput::make('coa_reference')
                    ->label('COA Reference'),
            ]);
    }
}
