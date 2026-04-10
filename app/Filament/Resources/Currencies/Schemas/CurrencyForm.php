<?php

namespace App\Filament\Resources\Currencies\Schemas;

use App\Enums\CurrencyExchangeRateType;
use App\Enums\CurrencyRoundingMethod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CurrencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Currency Configuration')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('code')
                                        ->label('ISO Code')
                                        ->placeholder('e.g., USD')
                                        ->required()
                                        ->maxLength(10)
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                    TextInput::make('description')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('symbol')
                                        ->placeholder('e.g., $')
                                        ->maxLength(10),
                                ]),
                                Grid::make(2)->schema([
                                    Section::make('Status & Type')
                                        ->schema([
                                            Toggle::make('is_active')
                                                ->label('Active')
                                                ->default(true),
                                            Toggle::make('is_lcy')
                                                ->label('Local Currency (LCY)')
                                                ->helperText('Only one currency can be the Local Currency.')
                                                ->reactive(),
                                        ])->compact()->inlineLabel(),
                                    Section::make('ISO Standards')
                                        ->schema([
                                            TextInput::make('iso_numeric_code')
                                                ->label('ISO Numeric Code')
                                                ->placeholder('e.g., 840')
                                                ->maxLength(3),
                                            TextInput::make('iso_country_code')
                                                ->label('ISO Country Code')
                                                ->placeholder('e.g., US')
                                                ->maxLength(10)
                                                ->helperText('Use Alpha-2 (NG) or Alpha-3 (NGA) codes.'),
                                        ])->compact(),
                                ]),
                            ]),

                        Tab::make('Rounding & Precision')
                            ->icon('heroicon-m-variable')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('decimal_places')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(5)
                                        ->default(2)
                                        ->required(),
                                    Select::make('rounding_method')
                                        ->options(CurrencyRoundingMethod::class)
                                        ->default(CurrencyRoundingMethod::NEAREST)
                                        ->required(),
                                    TextInput::make('amount_rounding_precision')
                                        ->numeric()
                                        ->step(0.0001)
                                        ->default(0.01)
                                        ->required(),
                                    TextInput::make('unit_amount_rounding_precision')
                                        ->numeric()
                                        ->step(0.00001)
                                        ->default(0.00001)
                                        ->required(),
                                ]),
                            ]),

                        Tab::make('Exchange Rates')
                            ->icon('heroicon-m-arrows-right-left')
                            ->hidden(fn (callable $get) => $get('is_lcy'))
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('exchange_rate')
                                        ->numeric()
                                        ->step(0.000001)
                                        ->default(1)
                                        ->required(),
                                    DatePicker::make('exchange_rate_date')
                                        ->default(now()),
                                    Select::make('exchange_rate_type')
                                        ->options(CurrencyExchangeRateType::class)
                                        ->default(CurrencyExchangeRateType::SPOT)
                                        ->required(),
                                ]),
                                TextInput::make('reporting_currency_code')
                                    ->label('Reporting Currency Code'),
                            ]),

                        Tab::make('G/L Posting')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Section::make('Realized Gains/Losses')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('realized_gains_account_id')
                                                ->relationship('realizedGainsAccount', 'name')
                                                ->searchable()
                                                ->preload(),
                                            Select::make('realized_losses_account_id')
                                                ->relationship('realizedLossesAccount', 'name')
                                                ->searchable()
                                                ->preload(),
                                        ]),
                                    ]),
                                Section::make('Unrealized Gains/Losses')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('unrealized_gains_account_id')
                                                ->relationship('unrealizedGainsAccount', 'name')
                                                ->searchable()
                                                ->preload(),
                                            Select::make('unrealized_losses_account_id')
                                                ->relationship('unrealizedLossesAccount', 'name')
                                                ->searchable()
                                                ->preload(),
                                        ]),
                                    ]),
                                Section::make('Balance Sheet Accounts')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('receivables_account_id')
                                                ->relationship('receivablesAccount', 'name')
                                                ->searchable(),
                                            Select::make('payables_account_id')
                                                ->relationship('payablesAccount', 'name')
                                                ->searchable(),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Invoicing & Tolerances')
                            ->icon('heroicon-m-receipt-percent')
                            ->schema([
                                Section::make('Payment Tolerance')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('payment_tolerance_percent')
                                                ->label('Tolerance %')
                                                ->numeric()
                                                ->suffix('%')
                                                ->default(0),
                                            TextInput::make('max_payment_tolerance_amount')
                                                ->label('Max Tolerance Amount')
                                                ->numeric(),
                                        ]),
                                    ]),
                                Section::make('Invoice Rounding')
                                    ->schema([
                                        Toggle::make('invoice_rounding')->label('Enable Invoice Rounding'),
                                        Grid::make(2)->schema([
                                            TextInput::make('invoice_rounding_precision')->numeric(),
                                            Select::make('invoice_rounding_account_id')
                                                ->relationship('invoiceRoundingAccount', 'name')
                                                ->searchable(),
                                        ]),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
