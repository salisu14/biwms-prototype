<?php

namespace App\Filament\Resources\ProductionBomVersions\Schemas;

use App\Enums\ProductionBomStatus;
use App\Models\Manufacturing\ProductionBomVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionBomVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('General Information')
                ->icon('heroicon-m-information-circle')
                ->columns(2)
                ->schema([

                    // ✅ BOM SELECT
                    Select::make('production_bom_id')
                        ->label('Parent BOM')
                        ->relationship('productionBom', 'description')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn ($state, $set) => $set('unit_of_measure_code', null)
                        ),

                    // ✅ VERSION CODE
                    TextInput::make('version_code')
                        ->label('Version Code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20)
                        ->placeholder('e.g., V1, V2024-01')
                        // Lock the field if the record already exists in the database
                        ->disabled(fn (?ProductionBomVersion $record) => $record !== null)
                        // Ensure the value is still sent to the database during creation
                        ->dehydrated()
                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                        ->helperText('The code cannot be changed once the Production bom version is created.'),

                    // ✅ DESCRIPTION
                    TextInput::make('description')
                        ->columnSpanFull()
                        ->maxLength(255),

                    // ✅ STATUS
                    Select::make('status')
                        ->options(ProductionBomStatus::class)
                        ->default(ProductionBomStatus::NEW)
                        ->required()
                        ->native(false),

                    // ✅ UOM SELECT (FIXED PROPERLY)
                    Select::make('unit_of_measure_code')
                        ->label('UOM')
                        ->relationship('unitOfMeasure', 'uom_code')
                        ->searchable()
                        ->preload()
                        ->required(),

                ]),

            Section::make('Configuration & Validity')
                ->icon('heroicon-m-calendar-days')
                ->columns(2)
                ->schema([

                    Grid::make(3)->schema([

                        // ✅ QUANTITY
                        TextInput::make('quantity_per')
                            ->label('Qty. per Parent')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(0.00001),

                        // ✅ COST ROLLUP (READ-ONLY)
                        TextInput::make('cost_rollup')
                            ->label('Last Cost Rollup')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false),

                        // ✅ START DATE
                        DatePicker::make('starting_date')
                            ->label('Active From')
                            ->native(false),

                        // ✅ END DATE
                        DatePicker::make('ending_date')
                            ->label('Active Until')
                            ->native(false)
                            ->after('starting_date'),

                    ]),
                ]),
        ]);
    }
}
