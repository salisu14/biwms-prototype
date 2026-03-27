<?php

namespace App\Filament\Resources\ItemSkus\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ItemSkuInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('item.id')
                    ->label('Item'),
                TextEntry::make('location.id')
                    ->label('Location'),
                TextEntry::make('sku_code'),
                TextEntry::make('reorder_point')
                    ->numeric(),
                TextEntry::make('safety_stock')
                    ->numeric(),
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
