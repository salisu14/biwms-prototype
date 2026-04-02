<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('item_number'),
                TextEntry::make('description'),
                TextEntry::make('description_2')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('generalProductPostingGroup.id')
                    ->label('General product posting group'),
                TextEntry::make('inventoryPostingGroup.id')
                    ->label('Inventory posting group'),
                TextEntry::make('vat_prod_posting_group')
                    ->placeholder('-'),
                TextEntry::make('item_type'),
                TextEntry::make('costing_method'),
                TextEntry::make('unit_cost')
                    ->money(),
                TextEntry::make('standard_cost')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('last_direct_cost')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('price_calculation_method'),
                TextEntry::make('profit_percent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('default_price_list_code')
                    ->placeholder('-'),
                IconEntry::make('allow_negative_price')
                    ->boolean(),
                TextEntry::make('unit_price')
                    ->money(),
                TextEntry::make('inventory')
                    ->numeric(),
                TextEntry::make('reorder_point')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('reorder_quantity')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('location.name')
                    ->label('Location')
                    ->placeholder('-'),
                TextEntry::make('bin_code')
                    ->placeholder('-'),
                TextEntry::make('base_unit_of_measure'),
                TextEntry::make('weight')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('volume')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('shelf_no')
                    ->placeholder('-'),
                TextEntry::make('item_tracking_code')
                    ->placeholder('-'),
                TextEntry::make('shelf_life_days')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                IconEntry::make('blocked')
                    ->boolean(),
                IconEntry::make('sales_blocked')
                    ->boolean(),
                IconEntry::make('purchasing_blocked')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
