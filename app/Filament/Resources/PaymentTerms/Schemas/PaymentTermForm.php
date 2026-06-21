<?php

namespace App\Filament\Resources\PaymentTerms\Schemas;

use App\Enums\PaymentTermsCalculation;
use App\Enums\PaymentTermsDiscountCalculation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PaymentTermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Payment Term Details')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                    TextInput::make('description')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, callable $set) => $set('search_description', $state)),
                                    TextInput::make('search_description')
                                        ->label('Search Description'),
                                ]),
                                Grid::make(2)->schema([
                                    Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true)
                                        ->onColor('success'),
                                    Toggle::make('blocked')
                                        ->label('Blocked')
                                        ->onColor('danger'),
                                ])->inlineLabel(),
                            ]),

                        Tab::make('Due Date Calculation')
                            ->icon('heroicon-m-calendar-days')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('calculation_type')
                                        ->label('Calculation Method')
                                        ->options(PaymentTermsCalculation::class)
                                        ->default(PaymentTermsCalculation::NET)
                                        ->required()
                                        ->live()
                                        ->native(false),

                                    TextInput::make('due_date_net_days')
                                        ->label('Net Days')
                                        ->numeric()
                                        ->default(0)
                                        ->visible(fn (callable $get) => in_array($get('calculation_type'), [
                                            PaymentTermsCalculation::NET->value,
                                            PaymentTermsCalculation::END_OF_MONTH->value,
                                            PaymentTermsCalculation::END_OF_NEXT_MONTH->value,
                                        ])),

                                    TextInput::make('due_date_day_of_month')
                                        ->label('Specific Day of Month')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(31)
                                        ->visible(fn (callable $get) => in_array($get('calculation_type'), [
                                            PaymentTermsCalculation::DUE_DATE->value,
                                            PaymentTermsCalculation::DUE_DAY->value,
                                        ])),

                                    TextInput::make('due_date_months_ahead')
                                        ->label('Months Ahead')
                                        ->numeric()
                                        ->default(0)
                                        ->visible(fn (callable $get) => $get('calculation_type') === PaymentTermsCalculation::DUE_DAY->value),
                                ]),
                            ]),

                        Tab::make('Cash Discount')
                            ->icon('heroicon-m-receipt-percent')
                            ->schema([
                                Toggle::make('discount_allowed')
                                    ->label('Enable Cash Discount')
                                    ->reactive(),

                                Grid::make(3)
                                    ->visible(fn (callable $get) => $get('discount_allowed'))
                                    ->schema([
                                        TextInput::make('discount_percent')
                                            ->label('Discount %')
                                            ->numeric()
                                            ->suffix('%')
                                            ->default(0),
                                        Select::make('discount_calculation_type')
                                            ->label('Discount Date Calculation')
                                            ->options(PaymentTermsDiscountCalculation::class)
                                            ->native(false),
                                        TextInput::make('discount_net_days')
                                            ->label('Discount Net Days')
                                            ->numeric()
                                            ->default(0),
                                    ]),

                                Select::make('discount_account_id')
                                    ->label('G/L Discount Account')
                                    ->relationship('discountAccount', 'name')
                                    ->searchable()
                                    ->visible(fn (callable $get) => $get('discount_allowed')),
                            ]),

                        Tab::make('Tolerances & Penalties')
                            ->icon('heroicon-m-exclamation-triangle')
                            ->schema([
                                Section::make('Payment Tolerance')
                                    ->compact()
                                    ->schema([
                                        Toggle::make('payment_tolerance_enabled')
                                            ->label('Enable Tolerance')
                                            ->reactive(),
                                        Grid::make(2)
                                            ->visible(fn (callable $get) => $get('payment_tolerance_enabled'))
                                            ->schema([
                                                TextInput::make('payment_tolerance_percent')
                                                    ->label('Tolerance %')
                                                    ->numeric()
                                                    ->suffix('%'),
                                                TextInput::make('max_payment_tolerance_amount')
                                                    ->label('Max Tolerance Amount')
                                                    ->numeric()
                                                    ->prefix('$'),
                                                Select::make('payment_tolerance_account_id')
                                                    ->label('Tolerance G/L Account')
                                                    ->relationship('toleranceAccount', 'name')
                                                    ->searchable()
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),

                                Section::make('Late Payment Penalties')
                                    ->compact()
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('late_payment_penalty_percent')
                                                ->label('Penalty %')
                                                ->numeric()
                                                ->suffix('%'),
                                            TextInput::make('late_payment_grace_days')
                                                ->label('Grace Period (Days)')
                                                ->numeric(),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Notes & Dimensions')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('shortcut_dimension_1_code')->label('Global Dimension 1'),
                                    TextInput::make('shortcut_dimension_2_code')->label('Global Dimension 2'),
                                ]),
                                Textarea::make('notes')->rows(3)->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
