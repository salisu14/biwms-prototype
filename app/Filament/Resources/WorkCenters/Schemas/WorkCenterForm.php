<?php

namespace App\Filament\Resources\WorkCenters\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Define the basic identity and grouping of the work center.')
                    ->icon('heroicon-o-inbox')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Work Center Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(20),

                            TextInput::make('name')
                                ->label('Work Center name')
                                ->required()
                                ->maxLength(200),

                            Select::make('work_center_group_id')
                                ->label('Group')
                                ->relationship('group', 'name') // Assuming WorkCenterGroup has a 'name' column
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('subcontractor_id')
                                ->label('Subcontractor')
                                ->relationship('subcontractor', 'vendor_name') // Assuming a Vendor relationship exists
                                ->searchable()
                                ->preload()
                                ->placeholder('Internal Work Center'),
                        ]),
                    ]),

                Section::make('Capacity & Efficiency')
                    ->description('Define capacity and operational efficiency.')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('unit_of_measure_code')
                                ->label('Time Unit')
                                ->options([
                                    'MINUTES' => 'Minutes',
                                    'HOURS' => 'Hours',
                                ])
                                ->required()
                                ->default('MINUTES'),

                            TextInput::make('capacity')
                                ->label('Capacity (Per Period)')
                                ->required()
                                ->numeric()
                                ->step(0.0001)
                                ->helperText('Available capacity per time unit.'),

                            TextInput::make('efficiency')
                                ->label('Current Efficiency %')
                                ->numeric()
                                ->suffix('%')
                                ->step(0.01)
                                ->default(100),

                            TextInput::make('maximum_efficiency')
                                ->label('Max Efficiency %')
                                ->numeric()
                                ->suffix('%')
                                ->step(0.01)
                                ->default(100),

                            TextInput::make('minimum_efficiency')
                                ->label('Min Efficiency %')
                                ->numeric()
                                ->suffix('%')
                                ->step(0.01)
                                ->default(0),
                        ]),

                        Placeholder::make('effective_capacity')
                            ->label('Effective Capacity')
                            ->content(function ($get) {
                                $cap = (float) ($get('capacity') ?? 0);
                                $eff = (float) ($get('efficiency') ?? 100);
                                $effective = $cap * ($eff / 100);
                                return number_format($effective, 4);
                            })
                            ->columnSpanFull()
                            ->helperText('Capacity * (Efficiency / 100)'),
                    ]),

                Section::make('Costing')
                    ->description('Cost rates for scheduling calculations.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('direct_unit_cost')
                                ->label('Direct Unit Cost')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.0001),

                            TextInput::make('indirect_cost_percent')
                                ->label('Indirect Cost %')
                                ->numeric()
                                ->suffix('%')
                                ->default(0),

                            TextInput::make('overhead_rate')
                                ->label('Overhead Rate ($/hr)')
                                ->numeric()
                                ->prefix('$')
                                ->step(0.0001),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Scheduling & Location')
                    ->description('Scheduling parameters and physical location.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('queue_time')
                                ->label('Queue Time')
                                ->numeric()
//                                ->suffix(fn ($get): string => $get('unit_of_measure_code')), // Show UoM suffix
                                ->helperText('Average time waiting to enter this center'),

                            TextInput::make('location_code')
                                ->label('Location Code')
                                ->maxLength(20),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Financials')
                    ->description('G/L Account configuration.')
                    ->schema([
                        TextInput::make('work_center_account_no')
                            ->label('G/L Account No')
                            ->required(),
                    ])
                    ->collapsible(),

                Hidden::make('created_by')
                    ->default(auth()->id()),
            ]);
    }
}
