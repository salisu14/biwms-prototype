<?php

namespace App\Filament\Resources\Vendors\Schemas;

use App\Models\Vendor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Vendor Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('General Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('vendor_code')
                                        ->label('Vendor No.')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->disabled(fn (?Vendor $record) => $record !== null)
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                                    TextInput::make('vendor_name')
                                        ->label('Name')
                                        ->required()
                                        ->columnSpan(2),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('contact_person'),
                                    Select::make('contact_id')
                                        ->label('Linked Contact Card')
                                        ->relationship('contact', 'name')
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('email')->email(),
                                    TextInput::make('phone')->tel(),
                                    TextInput::make('mobile')->tel(),
                                    TextInput::make('tax_id')->label('Tax Registration No.'),
                                ]),

                                Section::make('Address')
                                    ->collapsed()
                                    ->schema([
                                        Textarea::make('address')->rows(2),
                                        Grid::make(3)->schema([
                                            TextInput::make('city'),
                                            TextInput::make('state'),
                                            TextInput::make('postal_code'),
                                            TextInput::make('country'),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Posting & VAT')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Section::make('G/L & Business Grouping')
                                    ->description('Define how this vendor impacts the General Ledger.')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('general_business_posting_group_id')
                                            ->label('Gen. Bus. Posting Group')
                                            ->relationship('generalBusinessPostingGroup', 'code')
                                            ->required()
                                            ->searchable()
                                            ->preload(),

                                        Select::make('vendor_posting_group_id')
                                            ->label('Vendor Posting Group')
                                            ->relationship('vendorPostingGroup', 'code')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Determines the Accounts Payable G/L account.'),

                                        Select::make('vat_business_posting_group_id')
                                            ->label('VAT Bus. Posting Group')
                                            ->relationship('vatBusinessPostingGroup', 'code')
                                            ->required()
                                            ->searchable()
                                            ->preload(),

                                        TextInput::make('vat_bus_posting_group')
                                            ->label('VAT Code (Legacy)')
                                            ->placeholder('e.g., DOMESTIC'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Payments & Logistics')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('currency')
                                        ->label('Default Currency')
                                        ->options([
                                            'USD' => 'USD - US Dollar',
                                            'EUR' => 'EUR - Euro',
                                            'GBP' => 'GBP - British Pound',
                                            'NGN' => 'NGN - Nigerian Naira',
                                        ])
                                        ->default('USD')
                                        ->required(),

                                    TextInput::make('payment_terms_code')
                                        ->label('Payment Terms Code')
                                        ->placeholder('e.g., 1M(8D)'),

                                    TextInput::make('lead_time_days')
                                        ->label('Lead Time (Days)')
                                        ->numeric()
                                        ->default(0),

                                    TextInput::make('minimum_order_amount')
                                        ->label('Min. Order Amount')
                                        ->numeric()
                                        ->prefix('$')
                                        ->step(0.0001),

                                    Toggle::make('is_price_inclusive')
                                        ->label('Prices Include VAT')
                                        ->inline(false),
                                ]),
                            ]),

                        Tabs\Tab::make('Status & Notes')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('is_active')
                                        ->label('Active Status')
                                        ->default(true)
                                        ->onColor('success'),

                                    Toggle::make('blocked')
                                        ->label('Blocked')
                                        ->reactive()
                                        ->onColor('danger'),

                                    TextInput::make('blocked_reason')
                                        ->visible(fn ($get) => $get('blocked'))
                                        ->columnSpanFull(),
                                ]),

                                Textarea::make('notes')
                                    ->rows(5)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
