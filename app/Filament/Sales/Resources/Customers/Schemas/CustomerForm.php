<?php

namespace App\Filament\Sales\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required(),

                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),

                        TextInput::make('tax_registration_no')
                            ->maxLength(255)
                            ->label('Tax Registration No.'),
                    ])
                    ->columns(2),

                Section::make('Address')
                    ->schema([
                        TextInput::make('address')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('city')
                            ->maxLength(255),

                        TextInput::make('postal_code')
                            ->maxLength(50),

                        TextInput::make('country')
                            ->maxLength(255)
                            ->default('United States'),
                    ])
                    ->columns(3),

                Section::make('Sales Information')
                    ->schema([
                        Select::make('payment_terms_id')
                            ->relationship('paymentTerms', 'code')
                            ->preload()
                            ->searchable(),

                        Select::make('payment_method_id')
                            ->relationship('paymentMethod', 'code')
                            ->preload()
                            ->searchable(),

                        Select::make('assigned_salesperson_id')
                            ->relationship('salesperson', 'name')
                            ->preload()
                            ->searchable()
                            ->hidden(fn () => ! auth()->user()->hasAnyRole(['sales-manager', 'super-admin'])),

                        Toggle::make('is_blocked')
                            ->label('Blocked')
                            ->helperText('Prevent new transactions for this customer')
                            ->hidden(fn () => ! auth()->user()->hasAnyRole(['sales-manager', 'super-admin'])),
                    ])
                    ->columns(2),
            ]);
    }
}
