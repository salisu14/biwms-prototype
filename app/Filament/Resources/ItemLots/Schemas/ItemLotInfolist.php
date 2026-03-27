<?php

namespace App\Filament\Resources\ItemLots\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ItemLotInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('item.id')
                    ->label('Item'),
                TextEntry::make('lot_number'),
                TextEntry::make('supplier_lot')
                    ->placeholder('-'),
                TextEntry::make('receipt_date')
                    ->date(),
                TextEntry::make('expiry_date')
                    ->date(),
                TextEntry::make('retest_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('quantity_received')
                    ->numeric(),
                TextEntry::make('quantity_remaining')
                    ->numeric(),
                TextEntry::make('status'),
                TextEntry::make('coa_reference')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
