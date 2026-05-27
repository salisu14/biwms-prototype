<?php

namespace App\Filament\Sales\Resources\Items\Schemas;

use App\Models\Item;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('item_code'),
                TextEntry::make('description'),
                TextEntry::make('description_2')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('item_type')
                    ->badge(),
                TextEntry::make('inventory_method')
                    ->badge(),
                TextEntry::make('generalProductPostingGroup.id')
                    ->label('General product posting group'),
                TextEntry::make('inventoryPostingGroup.id')
                    ->label('Inventory posting group'),
                TextEntry::make('vat_prod_posting_group')
                    ->placeholder('-'),
                TextEntry::make('uom_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('sku_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('vat.id')
                    ->label('Vat')
                    ->placeholder('-'),
                TextEntry::make('general_posting_setup_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('inventory_posting_setup_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('vatProductPostingGroup.id')
                    ->label('Vat product posting group')
                    ->placeholder('-'),
                TextEntry::make('costing_method')
                    ->badge(),
                TextEntry::make('unit_cost')
                    ->money(),
                TextEntry::make('standard_cost')
                    ->money(),
                TextEntry::make('last_direct_cost')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('unit_price')
                    ->money(),
                TextEntry::make('profit_percent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('price_calculation_method'),
                TextEntry::make('default_price_list_code')
                    ->placeholder('-'),
                IconEntry::make('allow_negative_price')
                    ->boolean(),
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
                TextEntry::make('baseUom.id')
                    ->label('Base uom')
                    ->placeholder('-'),
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
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Item $record): bool => $record->trashed()),
                TextEntry::make('inventoryBin.id')
                    ->label('Inventory bin')
                    ->placeholder('-'),
                TextEntry::make('sku')
                    ->label('SKU')
                    ->placeholder('-'),
                TextEntry::make('currency.id')
                    ->label('Currency')
                    ->placeholder('-'),
                TextEntry::make('item_category_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('productionBom.id')
                    ->label('Production bom')
                    ->placeholder('-'),
                TextEntry::make('routing.id')
                    ->label('Routing')
                    ->placeholder('-'),
            ]);
    }
}
