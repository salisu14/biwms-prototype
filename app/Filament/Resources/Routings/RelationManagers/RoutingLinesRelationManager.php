<?php

namespace App\Filament\Resources\Routings\RelationManagers;

use App\Filament\Resources\Routings\RoutingResource;
use App\Models\Manufacturing\RoutingLine;
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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoutingLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = RoutingResource::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';
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
                            ->default(fn() => static::getNextOperationNumber($this->getOwnerRecord()->lines->max('operation_no') ?? 0))
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
                            ->label('Work Center')
                            ->relationship(name: 'workCenter', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('machine_center_id')
                            ->label('Machine Center')
                            ->relationship(name: 'machineCenter', titleAttribute: 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                // Section 1: Times & Capacities - Full Width, 2 columns for larger fields
                Section::make('Times & Capacities')
                    ->description('Production duration and capacity.')
                    ->columns(2)
                    ->schema([
                        // Setup Time - Full width row
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

                        // Run Time - Full width row
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

                        // Row 1: Wait | Move | Queue
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

                        // Row 2: Concurrent Cap | Lot Size | Fixed Scrap
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
                            ->numeric(),
                    ]),

                // Section 2: Costing & Outsourcing - Full Width, 2 columns for larger fields
                Section::make('Costing & Outsourcing')
                    ->description('Financial and external vendor details.')
                    ->columns(2)
                    ->schema([
                        // Row 1: Direct Cost | Indirect %
                        TextInput::make('direct_unit_cost')
                            ->label('Direct Unit Cost')
                            ->numeric()
                            ->prefix('$'),

                        TextInput::make('indirect_cost_percent')
                            ->label('Indirect %')
                            ->numeric()
                            ->suffix('%'),

                        // Row 2: Overhead Rate | Scrap %
                        TextInput::make('overhead_rate')
                            ->label('Overhead Rate')
                            ->numeric(),

                        TextInput::make('scrap_factor_percent')
                            ->label('Scrap %')
                            ->numeric()
                            ->suffix('%'),

                        // Row 3: Subcontractor (full width for long names)
                        Select::make('subcontractor_id')
                            ->label('Subcontractor (Vendor)')
                            ->relationship('routing', 'created_by')
                            ->searchable()
                            ->columnSpanFull(),

                        // Row 4: Subcontracting Cost (full width)
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
                TextColumn::make('operation_no')
                    ->label('Op.')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->searchable()
                    ->wrap()
                    ->description(fn(RoutingLine $record) => $record->routing_link_code ? "Link: {$record->routing_link_code}" : null),

                TextColumn::make('workCenter.name')
                    ->label('Work Center')
                    ->toggleable(),

                TextColumn::make('setup_time')
                    ->label('Setup')
                    ->formatStateUsing(fn($state, $record) => "{$state} {$record->setup_time_unit}")
                    ->toggleable(),

                TextColumn::make('run_time')
                    ->label('Run')
                    ->formatStateUsing(fn($state, $record) => "{$state} {$record->run_time_unit}")
                    ->toggleable(),

                TextColumn::make('direct_unit_cost')
                    ->label('Cost')
                    ->money('USD')
                    ->toggleable(),

                TextColumn::make('scrap_factor_percent')
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
        return $maxSoFar + 10000;
    }
}
