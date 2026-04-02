<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('item_number')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                Textarea::make('description_2')
                    ->columnSpanFull(),
                Select::make('general_product_posting_group_id')
                    ->relationship('generalProductPostingGroup', 'id')
                    ->required(),
                Select::make('inventory_posting_group_id')
                    ->relationship('inventoryPostingGroup', 'id')
                    ->required(),
                TextInput::make('vat_prod_posting_group'),
                TextInput::make('item_type')
                    ->required()
                    ->default('INVENTORY'),
                TextInput::make('costing_method')
                    ->required()
                    ->default('AVERAGE'),
                TextInput::make('unit_cost')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('standard_cost')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('last_direct_cost')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('price_calculation_method')
                    ->required()
                    ->default('STANDARD'),
                TextInput::make('profit_percent')
                    ->numeric(),
                TextInput::make('default_price_list_code'),
                Toggle::make('allow_negative_price')
                    ->required(),
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('inventory')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('reorder_point')
                    ->numeric(),
                TextInput::make('reorder_quantity')
                    ->numeric(),
                Select::make('location_id')
                    ->relationship('location', 'name'),
                TextInput::make('bin_code'),
                TextInput::make('base_unit_of_measure')
                    ->required()
                    ->default('PCS'),
                TextInput::make('weight')
                    ->numeric(),
                TextInput::make('volume')
                    ->numeric(),
                TextInput::make('shelf_no'),
                TextInput::make('item_tracking_code'),
                TextInput::make('shelf_life_days')
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('blocked')
                    ->required(),
                Toggle::make('sales_blocked')
                    ->required(),
                Toggle::make('purchasing_blocked')
                    ->required(),
            ]);
    }
}
