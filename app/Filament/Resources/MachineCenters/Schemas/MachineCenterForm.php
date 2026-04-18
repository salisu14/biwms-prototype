<?php

namespace App\Filament\Resources\MachineCenters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MachineCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Identify the machine and its associated work center.')
                    ->icon('heroicon-m-cpu-chip')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Machine Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g. MC-001'),

                                TextInput::make('name')
                                    ->label('Machine Name')
                                    ->required()
                                    ->columnSpan(2)
                                    ->placeholder('e.g. CNC Lathe 5-Axis'),

                                Select::make('work_center_id')
                                    ->relationship('workCenter', 'name')
                                    ->label('Parent Work Center')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('location_code')
                                    ->label('Shop Floor Location')
                                    ->relationship('location', 'code')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(1),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Capacity & Efficiency')
                            ->description('Operational limits for production.')
                            ->icon('heroicon-m-bolt')
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('capacity')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->suffix('Units/Hr')
                                    ->minValue(0),

                                TextInput::make('efficiency')
                                    ->numeric()
                                    ->default(100)
                                    ->required()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100),
                            ]),

                        Section::make('Timing & Prep')
                            ->description('Setup and lead time parameters.')
                            ->icon('heroicon-m-clock')
                            ->columnSpan(1)
                            ->schema([
                                // Removed the internal 3-column grid to allow fields to take full width of the section
                                TextInput::make('setup_time')
                                    ->label('Setup Time')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->suffix('minutes'),

                                TextInput::make('wait_time')
                                    ->label('Wait Time')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->suffix('minutes'),

                                TextInput::make('move_time')
                                    ->label('Move Time')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->suffix('minutes'),
                            ]),
                    ]),

                Section::make('Costing & Rates')
                    ->description('Financial parameters for calculating production costs.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('direct_unit_cost')
                                    ->label('Direct Unit Cost')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->prefix('₦')
                                    ->step(0.0001),

                                TextInput::make('overhead_rate')
                                    ->label('Overhead Rate')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->prefix('₦')
                                    ->step(0.0001),

                                TextInput::make('indirect_cost_percent')
                                    ->label('Indirect Cost %')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->suffix('%')
                                    ->minValue(0),

                                Select::make('fixed_asset_id')
                                    ->label('Linked Fixed Asset')
                                    ->relationship('fixedAsset', 'fa_no')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->fa_no} - {$record->description}")
                                    ->searchable()
                                    ->preload(),

                                Select::make('operator_employee_id')
                                    ->label('Primary Operator')
                                    ->relationship('operatorEmployee', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_number} - {$record->first_name} {$record->last_name}")
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
