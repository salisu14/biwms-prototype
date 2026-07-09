<?php

namespace App\Filament\Resources\ProductionBoms\Schemas;

use App\Models\Manufacturing\ProductionBom;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionBomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            // Lock the field if the record already exists in the database
                            ->disabled(fn (?ProductionBom $record) => $record !== null)
                            // Ensure the value is still sent to the database during creation
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->helperText('The code cannot be changed once the Production bom is created.'),

                        Select::make('status')
                            ->options([
                                'UNDER_DEVELOPMENT' => 'Under Development',
                                'CERTIFIED' => 'Certified',
                                'CLOSED' => 'Closed',
                            ])
                            ->required()
                            ->default('UNDER_DEVELOPMENT')
                            ->native(false),

                        TextInput::make('version')
                            ->placeholder('e.g. V1.0')
                            ->maxLength(20),

                        TextInput::make('description')
                            ->required()
                            ->columnSpan(2)
                            ->maxLength(255),

                        Select::make('item_id')
                            ->label('Parent Item')
                            ->relationship('item', 'description')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The main assembly or finished good this BOM describes.'),
                    ]),

                Section::make('BOM Configuration')
                    ->description('Units, dates, and calculation logic.')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('unit_of_measure_code')
                                    ->label('Base UOM')
                                    ->relationship('unitOfMeasure', 'uom_code')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                DatePicker::make('starting_date')
                                    ->native(false),

                                DatePicker::make('ending_date')
                                    ->native(false),

                                TextInput::make('low_level_code')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled()
                                    ->helperText('System-calculated depth level for BOM explosion.'),

                                TextInput::make('cost_rollup')
                                    ->label('Last Rollup Cost')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->disabled()
                                    ->placeholder('0.0000'),
                            ]),
                    ]),
            ]);
    }
}
