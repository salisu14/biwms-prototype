<?php

namespace App\Filament\Resources\ItemLedgers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ItemLedgerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->relationship('item', 'id')
                    ->required(),
                Select::make('location_id')
                    ->relationship('location', 'id')
                    ->required(),
                TextInput::make('doc_id')
                    ->required()
                    ->numeric(),
                Select::make('uom_id')
                    ->relationship('uom', 'id')
                    ->required(),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
                TextInput::make('entry_type')
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('unit_cost')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('balance_after')
                    ->numeric(),
                TextInput::make('cost_after')
                    ->numeric(),
                TextInput::make('lot_number'),
                DatePicker::make('expiry_date'),
            ]);
    }
}
