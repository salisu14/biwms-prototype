<?php

namespace App\Filament\Resources\Vendors\Schemas;

use App\Models\Vendor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('vendor_code')
                    ->label('Zone Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    // Lock the field if the record already exists in the database
                    ->disabled(fn (?Vendor $record) => $record !== null)
                    // Ensure the value is still sent to the database during creation
                    ->dehydrated()
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->helperText('The code cannot be changed once the Vendor is created.'),

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
                Toggle::make('is_price_inclusive')
                    ->label('Prices Include VAT'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
