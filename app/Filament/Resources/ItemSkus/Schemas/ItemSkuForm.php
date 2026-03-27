<?php

namespace App\Filament\Resources\ItemSkus\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemSkuForm
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
                TextInput::make('sku_code')
                    ->required(),
                TextInput::make('reorder_point')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('safety_stock')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
