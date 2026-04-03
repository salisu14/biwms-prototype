<?php

namespace App\Filament\Resources\FixedAssets\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FixedAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Asset Identification')
                    ->description('Primary details and categorization of the fixed asset.')
                    ->icon('heroicon-m-cube')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->required()
                                    ->unique(ignoreRecord: true),

                                Select::make('asset_type')
                                    ->options([
                                        'BUILDING' => 'Building',
                                        'MACHINERY' => 'Machinery',
                                        'VEHICLE' => 'Vehicle',
                                        'FURNITURE' => 'Furniture',
                                        'EQUIPMENT' => 'Equipment',
                                    ])
                                    ->required(),

                                Select::make('status')
                                    ->options([
                                        'ACTIVE' => 'Active',
                                        'DISPOSED' => 'Disposed',
                                        'MAINTENANCE' => 'Under Maintenance',
                                    ])
                                    ->required()
                                    ->default('ACTIVE'),

                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('parent_building_id')
                                    ->label('Location/Building')
                                    ->relationship('parentBuilding', 'description')
                                    ->searchable()
                                    ->placeholder('N/A'),
                            ]),
                    ]),

                Section::make('Depreciation & Accounting')
                    ->description('Financial valuation and depreciation settings.')
                    ->icon('heroicon-m-calculator')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('acquisition_cost')->numeric()->prefix('$')->required(),
                                DatePicker::make('acquisition_date')->required(),
                                TextInput::make('salvage_value')->numeric()->prefix('$')->default(0),

                                Select::make('depreciation_method')
                                    ->options([
                                        'STRAIGHT_LINE' => 'Straight Line',
                                        'DECLINING_BALANCE' => 'Declining Balance',
                                        'UNITS_OF_PRODUCTION' => 'Units of Production',
                                    ])
                                    ->required(),

                                TextInput::make('useful_life_years')->numeric()->label('Useful Life (Years)'),
                                TextInput::make('depreciation_rate')->numeric()->suffix('%'),

                                Select::make('asset_gl_account_id')
                                    ->relationship('assetAccount', 'name')
                                    ->label('Asset G/L Account')
                                    ->required(),

                                Select::make('accumulated_depreciation_gl_account_id')
                                    ->relationship('accumDepAccount', 'name')
                                    ->label('Accum. Dep. G/L Account')
                                    ->required(),

                                Select::make('depreciation_expense_gl_account_id')
                                    ->relationship('depExpenseAccount', 'name')
                                    ->label('Dep. Expense G/L Account')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Capacity & Efficiency')
                    ->description('Operational parameters for production allocation.')
                    ->icon('heroicon-m-bolt')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('annual_capacity_minutes')->numeric()->suffix('min'),
                                TextInput::make('efficiency_percent')->numeric()->suffix('%')->default(100),
                                TextInput::make('total_square_footage')->numeric()->suffix('sqft'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Audit Trail')
                    ->schema([
                        Hidden::make('created_by')
                            ->label('Created By')
                            ->default(auth()->id()),


                        Hidden::make('last_modified_by')
                            ->label('Last Modified By')
                            ->default(auth()->id()),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record !== null), // Only visible on edit
            ]);
    }
}
