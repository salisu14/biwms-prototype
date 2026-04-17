<?php

namespace App\Filament\Resources\WorkCenters\Schemas;

use App\Models\ChartOfAccount;
use App\Models\Manufacturing\WorkCenter;
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
                                ->label('WorkCenter Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                // Lock the field if the record already exists in the database
                                ->disabled(fn (?WorkCenter $record) => $record !== null)
                                // Ensure the value is still sent to the database during creation
                                ->dehydrated()
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->helperText('The code cannot be changed once the work center is created.'),

                            TextInput::make('name')
                                ->label('Work Center name')
                                ->required()
                                ->maxLength(200),

                            Select::make('work_center_group_id')
                                ->label('Group')
                                ->relationship('group', 'name')
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
                                ->relationship('unitOfMeasure', 'uom_code')
                                ->searchable()
                                ->preload()
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
                                ->required()
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
                                ->required()
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
                                ->required()
                                ->numeric()
//                                ->suffix(fn ($get): string => $get('unit_of_measure_code')), // Show UoM suffix
                                ->helperText('Average time waiting to enter this center'),

                            Select::make('location_code')
                                ->label('Location')
                                ->relationship('location', 'code')
                                ->searchable()
                                ->preload()
                                ->maxLength(20),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Financials')
                    ->description('G/L Account configuration for WIP and capacity cost posting.')
                    ->schema([
                        Select::make('work_center_gl_account_id')
                            ->label('G/L Account')
                            ->relationship(
                                name: 'glAccount',
                                titleAttribute: 'account_number',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('direct_posting', true)
                                    ->where('blocked', false),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}"
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the posting account for WIP and capacity costs (must allow direct posting).'),
                    ])
                    ->collapsible(),

                Hidden::make('created_by')
                    ->default(auth()->id()),
            ]);
    }
}
