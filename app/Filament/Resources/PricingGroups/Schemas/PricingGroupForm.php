<?php

namespace App\Filament\Resources\PricingGroups\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PricingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Group Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(100),
                            ]),
                        Textarea::make('description')
                            ->columnSpanFull()
                            ->maxLength(255),
                    ]),

                Section::make('Pricing Strategy & Margins')
                    ->description('Define how pricing is calculated and enforced for this group.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('pricing_strategy')
                                    ->label('Pricing Strategy')
                                    ->options([
                                        'STANDARD' => 'Standard Pricing',
                                        'COST_PLUS' => 'Cost Plus Markup',
                                        'DISCOUNT' => 'Discount from List',
                                        'MARGIN' => 'Margin Based',
                                    ])
                                    ->required()
                                    ->default('STANDARD')
                                    ->native(false),

                                TextInput::make('default_discount_percent')
                                    ->label('Default Discount %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->suffix('%'),

                                TextInput::make('default_markup_percent')
                                    ->label('Default Markup %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->suffix('%'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Toggle::make('enforce_minimum_margin')
                                    ->label('Enforce Minimum Margin')
                                    ->inline(false)
                                    ->live()
                                    ->helperText('Prevent sales below the minimum margin threshold.'),

                                TextInput::make('minimum_margin_percent')
                                    ->label('Minimum Margin %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->visible(fn (Get $get) => $get('enforce_minimum_margin') === true)
                                    ->required(fn (Get $get) => $get('enforce_minimum_margin') === true),

                                Toggle::make('allow_manual_override')
                                    ->label('Allow Manual Override')
                                    ->inline(false)
                                    ->default(true)
                                    ->helperText('Allow users to manually change prices on orders.'),
                            ]),
                    ]),

                Section::make('Validity & Accounting')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('currency_code')
                                    ->label('Currency')
                                    ->options([
                                        'NGN' => 'NGN (₦)',
                                        'USD' => 'USD ($)',
                                        'EUR' => 'EUR (€)',
                                        'GBP' => 'GBP (£)',
                                    ])
                                    ->required()
                                    ->default('NGN')
                                    ->native(false),

                                DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->native(false)
                                    ->live(),

                                DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->native(false)
                                    ->minDate(fn (Get $get) => $get('start_date')),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('general_business_posting_group_id')
                                    ->label('Gen. Bus. Posting Group')
                                    ->relationship('generalBusinessPostingGroup', 'code') // Assuming 'code' is the display attribute
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Determines G/L accounts for this group.'),

                                Toggle::make('blocked')
                                    ->label('Blocked')
                                    ->inline(false)
                                    ->default(false)
                                    ->helperText('Blocked groups cannot be selected on new documents.'),
                            ]),
                    ]),
            ]);
    }
}
