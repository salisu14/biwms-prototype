<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Section::make('General Information')
                            ->schema([
                                TextInput::make('customer_number')
                                    ->label('Customer No.')
                                    ->unique(ignoreRecord: true)
                                    ->readOnlyOn('create')
                                    ->placeholder('Auto-generated from Number Series (CUSTOMER)'),

                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                // Added: Customer Group selection
                                Select::make('customer_group_id')
                                    ->label('Customer Group')
                                    ->relationship('group', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select a group'),

                                Select::make('contact_id')
                                    ->label('Customer Contact (Optional)')
                                    ->relationship('contact', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->helperText('If empty, a contact will be auto-created from customer details.'),

                                Grid::make(2)->schema([
                                    TextInput::make('email')
                                        ->email()
                                        ->prefixIcon('heroicon-m-envelope'),
                                    TextInput::make('phone')
                                        ->tel()
                                        ->prefixIcon('heroicon-m-phone'),
                                ]),
                                Textarea::make('address')
                                    ->rows(3),
                            ])->columnSpan(2),

                        Section::make('Status & Credit')
                            ->schema([
                                Toggle::make('blocked')
                                    ->live(),

                                Select::make('blocked_reason')
                                    ->options([
                                        'NONE' => 'None',
                                        'SHIP' => 'Shipping Blocked',
                                        'INVOICE' => 'Invoice Blocked',
                                        'ALL' => 'Fully Blocked',
                                    ])
                                    ->visible(fn ($get) => $get('blocked'))
                                    ->required(fn ($get) => $get('blocked')),

                                TextInput::make('credit_limit')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0),

                                Select::make('location_id')
                                    ->relationship('location', 'name')
                                    ->searchable()
                                    ->preload(),
                            ])->columnSpan(1),
                    ]),

                Section::make('Posting Setup')
                    ->description('Define how transactions are recorded in the general ledger')
                    ->columns(3)
                    ->schema([
                        Select::make('general_business_posting_group_id')
                            ->label('Gen. Bus. Posting Group')
                            ->relationship('generalBusinessPostingGroup', 'description')
                            ->required(),
                        Select::make('customer_posting_group_id')
                            ->label('Customer Posting Group')
                            ->relationship('customerPostingGroup', 'description')
                            ->required(),
                        TextInput::make('vat_bus_posting_group')
                            ->label('VAT Bus. Posting Group'),
                    ]),

                Section::make('Shipping & Payments')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('shipping_agent_code'),
                        TextInput::make('payment_terms_code'),
                        Grid::make(3)->schema([
                            Toggle::make('allow_discounts')
                                ->default(true),
                            TextInput::make('maximum_discount_percent')
                                ->numeric()
                                ->suffix('%'),
                            Toggle::make('is_price_inclusive')
                                ->label('Prices Include VAT'),
                        ]),
                    ]),
            ]);
    }
}
