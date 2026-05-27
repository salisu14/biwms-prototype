<?php

namespace App\Filament\Sales\Resources\SalesQuotes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SalesQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('quote_no'),
                TextEntry::make('customer.name')
                    ->label('Customer'),
                TextEntry::make('quote_date')
                    ->date(),
                TextEntry::make('valid_until')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('approval_status'),
                TextEntry::make('approved_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                IconEntry::make('is_price_inclusive')
                    ->boolean(),
            ]);
    }
}
