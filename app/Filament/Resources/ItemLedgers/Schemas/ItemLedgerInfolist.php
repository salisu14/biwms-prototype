<?php

namespace App\Filament\Resources\ItemLedgers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ItemLedgerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('item.id')
                    ->label('Item'),
                TextEntry::make('location.id')
                    ->label('Location'),
                TextEntry::make('doc_id')
                    ->numeric(),
                TextEntry::make('uom.id')
                    ->label('Uom'),
                TextEntry::make('created_by')
                    ->numeric(),
                TextEntry::make('entry_type'),
                TextEntry::make('quantity')
                    ->numeric(),
                TextEntry::make('unit_cost')
                    ->money(),
                TextEntry::make('balance_after')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('cost_after')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('lot_number')
                    ->placeholder('-'),
                TextEntry::make('expiry_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
