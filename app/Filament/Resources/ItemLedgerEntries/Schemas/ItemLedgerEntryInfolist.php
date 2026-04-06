<?php

namespace App\Filament\Resources\ItemLedgerEntries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ItemLedgerEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('entry_number')
                    ->numeric(),
                TextEntry::make('entry_type'),
                TextEntry::make('document_type')
                    ->placeholder('-'),
                TextEntry::make('document_number'),
                TextEntry::make('document_line_number')
                    ->numeric(),
                TextEntry::make('item.id')
                    ->label('Item'),
                TextEntry::make('variant_code')
                    ->placeholder('-'),
                TextEntry::make('location.name')
                    ->label('Location'),
                TextEntry::make('bin_code')
                    ->placeholder('-'),
                TextEntry::make('quantity')
                    ->numeric(),
                TextEntry::make('remaining_quantity')
                    ->numeric(),
                TextEntry::make('serial_number')
                    ->placeholder('-'),
                TextEntry::make('lot_number')
                    ->placeholder('-'),
                TextEntry::make('expiration_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('cost_amount_actual')
                    ->numeric(),
                TextEntry::make('cost_amount_expected')
                    ->numeric(),
                TextEntry::make('purchase_amount_actual')
                    ->numeric(),
                TextEntry::make('generalBusinessPostingGroup.id')
                    ->label('General business posting group')
                    ->placeholder('-'),
                TextEntry::make('generalProductPostingGroup.id')
                    ->label('General product posting group'),
                TextEntry::make('inventoryPostingGroup.id')
                    ->label('Inventory posting group'),
                TextEntry::make('posting_date')
                    ->date(),
                TextEntry::make('entry_date')
                    ->dateTime(),
                TextEntry::make('appliedEntry.id')
                    ->label('Applied entry')
                    ->placeholder('-'),
                IconEntry::make('open')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('source_type')
                    ->placeholder('-'),
                TextEntry::make('source_id')
                    ->numeric()
                    ->placeholder('-'),
            ]);
    }
}
