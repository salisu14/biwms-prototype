<?php

namespace App\Filament\Resources\ItemLots\Schemas;

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
                    ->relationship('item', 'id')
                    ->required(),
                TextInput::make('lot_number')
                    ->required(),
                TextInput::make('supplier_lot'),
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
                TextInput::make('coa_reference'),
            ]);
    }
}
