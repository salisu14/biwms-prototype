<?php

namespace App\Filament\Resources\VatMasters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VatMasterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('description'),
                TextEntry::make('purchase_account_number'),
                TextEntry::make('sales_account_number'),
                TextEntry::make('percentage')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
