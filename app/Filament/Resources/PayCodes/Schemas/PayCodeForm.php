<?php

namespace App\Filament\Resources\PayCodes\Schemas;

use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Identification')
                    ->description('Primary naming and classification for this pay component.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Pay Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., BASIC_PAY'),

                        TextInput::make('name')
                            ->label('Display Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Monthly Basic Salary'),

                        Select::make('type')
                            ->label('Component Type')
                            ->options(PayCodeType::class)
                            ->enum(PayCodeType::class)
                            ->required()
                            ->native(false),

                        Grid::make(2)
                            ->schema([
                                Toggle::make('taxable')
                                    ->label('Subject to Tax')
                                    ->default(true)
                                    ->inline(false),

                                Toggle::make('is_statutory')
                                    ->label('Statutory Deduction')
                                    ->default(false)
                                    ->inline(false),
                            ]),
                    ]),

                Section::make('Calculation & Defaults')
                    ->description('Configure how this component is calculated and set initial values.')
                    ->columns(2)
                    ->schema([
                        Select::make('calculation_method')
                            ->label('Calculation Method')
                            ->options(CalculationMethod::class)
                            ->enum(CalculationMethod::class)
                            ->required()
                            ->reactive()
                            ->native(false),

                        TextInput::make('default_amount')
                            ->label('Default Fixed Amount')
                            ->numeric()
                            ->prefix('$')
                            ->visible(fn ($get) => $get('calculation_method') === CalculationMethod::FIXED_AMOUNT->value)
                            ->placeholder('0.00'),

                        TextInput::make('default_percentage')
                            ->label('Default Percentage')
                            ->numeric()
                            ->suffix('%')
                            ->visible(fn ($get) => $get('calculation_method') === CalculationMethod::PERCENTAGE->value)
                            ->placeholder('0.00'),
                    ]),

                Section::make('Accounting Mapping')
                    ->description('Link this pay code to the General Ledger.')
                    ->schema([
                        Select::make('gl_account_id')
                            ->label('G/L Account Mapping')
                            ->relationship('glAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Assign the Chart of Account where expenses or payables for this code will be posted.'),
                    ]),
            ]);
    }
}
