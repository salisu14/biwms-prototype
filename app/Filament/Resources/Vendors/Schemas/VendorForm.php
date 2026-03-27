<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('vendor_code')
                    ->required(),
                TextInput::make('vendor_name')
                    ->required(),
                TextInput::make('contact_person'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('mobile'),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('city'),
                TextInput::make('state'),
                TextInput::make('postal_code'),
                TextInput::make('country'),
                TextInput::make('tax_id'),
                TextInput::make('payment_terms'),
                TextInput::make('currency')
                    ->required()
                    ->default('USD'),
                TextInput::make('lead_time_days')
                    ->numeric(),
                TextInput::make('minimum_order_amount')
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
