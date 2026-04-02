<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_number')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                Select::make('general_business_posting_group_id')
                    ->relationship('generalBusinessPostingGroup', 'id')
                    ->required(),
                Select::make('customer_posting_group_id')
                    ->relationship('customerPostingGroup', 'id')
                    ->required(),
                TextInput::make('vat_bus_posting_group'),
                Select::make('location_id')
                    ->relationship('location', 'name'),
                TextInput::make('shipping_agent_code'),
                TextInput::make('payment_terms_code'),
                TextInput::make('credit_limit')
                    ->numeric(),
                Toggle::make('blocked')
                    ->required(),
                TextInput::make('blocked_reason')
                    ->required()
                    ->default('NONE'),
                TextInput::make('pricing_group_id')
                    ->numeric(),
                TextInput::make('price_list_code'),
                Toggle::make('allow_discounts')
                    ->required(),
                TextInput::make('maximum_discount_percent')
                    ->numeric(),
                Toggle::make('price_includes_vat')
                    ->required(),
            ]);
    }
}
