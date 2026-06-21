<?php

namespace App\Filament\Resources\DepreciationBooks\Schemas;

use App\Enums\DepreciationCalculationMethod;
use App\Enums\DepreciationMethod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepreciationBookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Primary configuration for the depreciation book.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Book Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., FADB-001'),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Main Corporate Book'),

                        Select::make('book_type')
                            ->label('Book Type')
                            ->required()
                            ->options([
                                'corporate' => 'Corporate',
                                'tax' => 'Tax',
                                'accounting' => 'Accounting',
                                'gaap' => 'US GAAP',
                                'ifrs' => 'IFRS',
                                'custom' => 'Custom',
                            ])
                            ->helperText('Select the type. Lowercase keys are used to match standard DB check constraints.'),

                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_default')
                                    ->label('Default Book')
                                    ->inline(false),

                                Toggle::make('is_active')
                                    ->label('Active Status')
                                    ->default(true)
                                    ->inline(false),
                            ]),
                    ]),

                Section::make('Depreciation Logic')
                    ->description('Set the default behavior for assets assigned to this book.')
                    ->columns(2)
                    ->schema([
                        Select::make('default_depreciation_method')
                            ->label('Depreciation Method')
                            ->options(DepreciationMethod::class)
                            ->required()
                            ->enum(DepreciationMethod::class),

                        Select::make('default_calculation_method')
                            ->label('Calculation Method')
                            ->options(DepreciationCalculationMethod::class)
                            ->required()
                            ->enum(DepreciationCalculationMethod::class),
                    ]),

                Section::make('Accounting Integration')
                    ->columns(3)
                    ->schema([
                        Toggle::make('integrate_with_gl')
                            ->label('G/L Integration')
                            ->helperText('Post transactions to General Ledger'),

                        Toggle::make('use_rounding')
                            ->label('Enable Rounding')
                            ->reactive(),

                        TextInput::make('rounding_precision')
                            ->label('Precision')
                            ->numeric()
                            ->default(2)
                            ->dehydrated() // Ensures field is sent even if hidden by reactive logic
                            ->hidden(fn ($get) => ! $get('use_rounding'))
                            ->required(fn ($get) => $get('use_rounding')),
                    ]),

                Section::make('Fiscal Calendar')
                    ->columns(2)
                    ->schema([
                        Toggle::make('align_fiscal_year')
                            ->label('Align with Fiscal Year')
                            ->reactive(),

                        Select::make('fiscal_year_start')
                            ->label('Fiscal Year Start Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->visible(fn ($get) => $get('align_fiscal_year'))
                            ->required(fn ($get) => $get('align_fiscal_year')),
                    ]),
            ]);
    }
}
