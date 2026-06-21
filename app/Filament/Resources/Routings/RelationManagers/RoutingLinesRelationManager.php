<?php

namespace App\Filament\Resources\Routings\RelationManagers;

use App\Filament\Resources\Routings\RoutingResource;
use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\RoutingLine;
use App\Models\Manufacturing\WorkCenter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class RoutingLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = RoutingResource::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $pluralLabel = 'Routing Lines';

    protected static ?string $title = 'Routing Lines';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('General Information')
                    ->columns(12)
                    ->schema([
                        TextInput::make('operation_no')
                            ->label('Op. No.')
                            ->numeric()
                            ->required()
                            ->default(fn () => static::getNextOperationNumber($this->getOwnerRecord()->lines->max('operation_no') ?? 0))
                            ->columnSpan(2),

                        TextInput::make('description')
                            ->label('Operation Description')
                            ->required()
                            ->placeholder('e.g., Cutting, Assembly, QC...')
                            ->columnSpan(7),

                        TextInput::make('routing_link_code')
                            ->label('Link Code')
                            ->helperText('Connects to BOM components')
                            ->columnSpan(3),
                    ]),

                Section::make('Work & Machine Centers')
                    ->columns(2)
                    ->schema([
                        Select::make('work_center_id')
                            ->label('Work Center (Where)')
                            ->relationship(name: 'workCenter', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $workCenter = WorkCenter::find($state);
                                    if ($workCenter) {
                                        // Auto-populate default cost structures from the master card
                                        $set('direct_unit_cost', $workCenter->direct_unit_cost ?? 0);
                                        $set('indirect_cost_percent', $workCenter->indirect_cost_percent ?? 0);
                                        $set('overhead_rate', $workCenter->overhead_rate ?? 0);
                                    }
                                }
                            })
                            ->helperText('Defines the default cost and capacity parameters for this step.'),

                        Select::make('machine_center_id')
                            ->label('Machine Center (Specific Machine)')
                            ->relationship(name: 'machineCenter', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $machineCenter = MachineCenter::find($state);
                                    if ($machineCenter) {
                                        // Specific machine centers can override general work center costs
                                        $set('direct_unit_cost', $machineCenter->direct_unit_cost ?? 0);
                                        $set('indirect_cost_percent', $machineCenter->indirect_cost_percent ?? 0);
                                        $set('overhead_rate', $machineCenter->overhead_rate ?? 0);
                                    }
                                }
                            })
                            ->helperText('Optional. Selection will override general Work Center costs.'),
                    ]),

                Section::make('Times & Capacities')
                    ->description('Production duration and capacity.')
                    ->columns(2)
                    ->schema([
                        // Setup Time - Wide Row Layout
                        Group::make()->schema([
                            TextInput::make('setup_time')
                                ->numeric()
                                ->label('Setup Time')
                                ->default(0)
                                ->columnSpan(2),
                            Select::make('setup_time_unit')
                                ->label('Unit')
                                ->relationship('setupTimeUnit', 'uom_code')
                                ->default('MINUTES')
                                ->columnSpan(1),
                        ])->columns(3)->columnSpanFull(),

                        // Run Time - Wide Row Layout
                        Group::make()->schema([
                            TextInput::make('run_time')
                                ->numeric()
                                ->label('Run Time')
                                ->required()
                                ->default(0)
                                ->columnSpan(2),
                            Select::make('run_time_unit')
                                ->label('Unit')
                                ->relationship('runTimeUnit', 'uom_code')
                                ->default('MINUTES')
                                ->columnSpan(1),
                        ])->columns(3)->columnSpanFull(),

                        TextInput::make('wait_time')
                            ->label('Wait Time')
                            ->numeric()
                            ->suffix('mins'),

                        TextInput::make('move_time')
                            ->label('Move Time')
                            ->numeric()
                            ->suffix('mins'),

                        TextInput::make('queue_time')
                            ->label('Queue Time')
                            ->numeric()
                            ->suffix('mins'),

                        TextInput::make('concurrent_capacities')
                            ->label('Concurrent Cap.')
                            ->numeric()
                            ->default(1),

                        TextInput::make('lot_size')
                            ->label('Lot Size')
                            ->numeric()
                            ->default(1),

                        TextInput::make('fixed_scrap_quantity')
                            ->label('Fixed Scrap')
                            ->numeric()
                            ->default(0),
                    ]),

                Section::make('Costing & Outsourcing')
                    ->description('Financial and external vendor details.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('direct_unit_cost')
                            ->label('Direct Unit Cost')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Pulled automatically from selected Work/Machine center.'),

                        TextInput::make('indirect_cost_percent')
                            ->label('Indirect Cost %')
                            ->numeric()
                            ->suffix('%')
                            ->helperText('Pulled automatically from selected Work/Machine center.'),

                        TextInput::make('overhead_rate')
                            ->label('Overhead Rate')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Pulled automatically from selected Work/Machine center.'),

                        TextInput::make('scrap_factor_percent')
                            ->label('Scrap %')
                            ->numeric()
                            ->suffix('%'),

                        Select::make('subcontractor_id')
                            ->label('Subcontractor (Vendor)')
                            ->relationship('routing', 'created_by')
                            ->searchable()
                            ->columnSpanFull(),

                        TextInput::make('subcontracting_cost')
                            ->label('Subcon. Cost')
                            ->numeric()
                            ->prefix('$')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('operation_no', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('operation_no')
                    ->label('Op.')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->wrap()
                    ->description(fn (RoutingLine $record) => $record->routing_link_code ? "Link: {$record->routing_link_code}" : null),

                Tables\Columns\TextColumn::make('workCenter.name')
                    ->label('Work Center')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('setup_time')
                    ->label('Setup')
                    ->formatStateUsing(fn ($state, $record) => "{$state} ".($record->setup_time_unit ?? 'MINUTES'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('run_time')
                    ->label('Run')
                    ->formatStateUsing(fn ($state, $record) => "{$state} ".($record->run_time_unit ?? 'MINUTES'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('direct_unit_cost')
                    ->label('Cost')
                    ->money('USD')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scrap_factor_percent')
                    ->label('Scrap %')
                    ->suffix('%')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create Routing Line'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Helper to get the next operation number
     */
    protected static function getNextOperationNumber(int $maxSoFar): int
    {
        return $maxSoFar + 10;
    }
}
