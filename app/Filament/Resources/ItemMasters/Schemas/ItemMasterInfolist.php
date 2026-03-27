<?php

namespace App\Filament\Resources\ItemMasters\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ItemMasterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('item_code'),
                TextEntry::make('description'),
                TextEntry::make('category.id')
                    ->label('Category'),
                TextEntry::make('baseUom.id')
                    ->label('Base uom'),
                TextEntry::make('standard_cost')
                    ->money(),
                TextEntry::make('shelf_life_days')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('is_active')
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
