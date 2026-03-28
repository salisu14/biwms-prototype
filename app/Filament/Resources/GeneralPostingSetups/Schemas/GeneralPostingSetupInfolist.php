<?php

namespace App\Filament\Resources\GeneralPostingSetups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class GeneralPostingSetupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('description'),
                TextEntry::make('sales_account'),
                TextEntry::make('sales_credit_account')
                    ->placeholder('-'),
                TextEntry::make('sales_discount_account')
                    ->placeholder('-'),
                TextEntry::make('purchase_account'),
                TextEntry::make('purchase_credit_account')
                    ->placeholder('-'),
                TextEntry::make('purchase_discount_account')
                    ->placeholder('-'),
                TextEntry::make('cogs_account'),
                TextEntry::make('purchase_variance_account')
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
