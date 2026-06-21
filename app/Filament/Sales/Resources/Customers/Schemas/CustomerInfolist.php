<?php

namespace App\Filament\Sales\Resources\Customers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('customer_number'),
                TextEntry::make('name'),
                TextEntry::make('address')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('generalBusinessPostingGroup.id')
                    ->label('General business posting group'),
                TextEntry::make('customerPostingGroup.id')
                    ->label('Customer posting group'),
                TextEntry::make('vat_bus_posting_group')
                    ->placeholder('-'),
                TextEntry::make('customer_group_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('location.name')
                    ->label('Location')
                    ->placeholder('-'),
                TextEntry::make('shipping_agent_code')
                    ->placeholder('-'),
                TextEntry::make('payment_terms_code')
                    ->placeholder('-'),
                TextEntry::make('credit_limit')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('blocked')
                    ->boolean(),
                TextEntry::make('blocked_reason'),
                TextEntry::make('contact.name')
                    ->label('Contact'),
                TextEntry::make('pricing_group_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('vatBusinessPostingGroup.id')
                    ->label('Vat business posting group')
                    ->placeholder('-'),
                TextEntry::make('price_list_code')
                    ->placeholder('-'),
                IconEntry::make('allow_discounts')
                    ->boolean(),
                TextEntry::make('maximum_discount_percent')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('price_includes_vat')
                    ->boolean(),
                TextEntry::make('customer_type')
                    ->badge()
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
