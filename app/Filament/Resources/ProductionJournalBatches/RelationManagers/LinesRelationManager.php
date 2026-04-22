<?php

namespace App\Filament\Resources\ProductionJournalBatches\RelationManagers;

use App\Enums\ProductionJournalEntryType;
use App\Filament\Resources\ProductionJournalBatches\ProductionJournalBatchResource;
use App\Models\Item;
use App\Models\Manufacturing\RoutingLine;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = ProductionJournalBatchResource::class;

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_no')
                        ->label('Line No.')
                        ->numeric()
                        ->required()
                        ->default(fn ($livewire) => ($livewire->ownerRecord->lines()->max('line_no') ?? 0) + 10),

                    DatePicker::make('posting_date')
                        ->label('Posting Date')
                        ->required()
                        ->default(now())
                        ->native(false),

                    Select::make('entry_type')
                        ->label('Entry Type')
                        ->options(ProductionJournalEntryType::class)
                        ->required()
                        ->native(false)
                        ->live(),
                ]),

                Section::make('Resource Selection')
                    ->columns(2)
                    ->schema([
                        Select::make('item_id')
                            ->label('Item / Component')
                            ->relationship('item', 'item_code')
                            ->getOptionLabelFromRecordUsing(fn (Item $record) => "{$record->item_code} – {$record->description}")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (!$state) return;
                                $item = Item::find($state);
                                if ($item) {
                                    $set('description', $item->description);
                                    $set('unit_of_measure_code', $item->uom?->uom_code);
                                    $set('unit_cost', $item->unit_cost);
                                }
                            }),

                        Select::make('routing_line_id')
                            ->label('Operation (Routing Link)')
                            ->relationship('routingLine', 'operation_no', fn ($query, $livewire) =>
                            $query->where('production_order_id', $livewire->ownerRecord->production_order_id)
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (!$state) return;
                                $routing = RoutingLine::find($state);
                                if ($routing) {
                                    $set('work_center_id', $routing->work_center_id);
                                    $set('machine_center_id', $routing->machine_center_id);
                                    $set('description', "Op {$routing->operation_no}: {$routing->description}");
                                }
                            }),

                        TextInput::make('description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Quantities')
                        ->columnSpan(1)
                        ->schema([
                            TextInput::make('quantity')
                                ->label('Consumption Qty')
                                ->numeric()
                                ->visible(fn ($get) => $get('entry_type') === ProductionJournalEntryType::Consumption->value)
                                ->helperText('Materials used in production.'),

                            TextInput::make('output_quantity')
                                ->label('Output Qty')
                                ->numeric()
                                ->visible(fn ($get) => $get('entry_type') === ProductionJournalEntryType::Output->value)
                                ->helperText('Finished goods produced.'),

                            TextInput::make('scrap_quantity')
                                ->label('Scrap Qty')
                                ->numeric()
                                ->default(0),
                        ]),

                    Section::make('Capacity & Time')
                        ->columnSpan(1)
                        ->visible(fn ($get) => $get('entry_type') === ProductionJournalEntryType::Output->value)
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('setup_time')->numeric()->suffix('min'),
                                TextInput::make('run_time')->numeric()->suffix('min'),
                            ]),
                            Select::make('work_center_id')
                                ->relationship('workCenter', 'name'),
                        ]),
                ]),

                Section::make('Calculated Costing')
                    ->columns(3)
                    ->schema([
                        TextInput::make('unit_cost')
                            ->numeric()
                            ->prefix('$')
                            ->live(onBlur: true),

                        TextInput::make('total_cost')
                            ->numeric()
                            ->prefix('$')
                            ->helperText('Auto-calculated on post if left empty.'),

                        Placeholder::make('cost_preview')
                            ->label('Estimated Line Cost')
                            ->content(function ($get) {
                                $qty = (float) ($get('quantity') ?? $get('output_quantity') ?? 0);
                                $unit = (float) ($get('unit_cost') ?? 0);
                                return '$ ' . number_format($qty * $unit, 4);
                            }),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_no')->label('Line')->sortable(),

                TextColumn::make('entry_type')
                    ->badge(),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->weight('bold')
                    ->description(fn ($record) => $record->description),

                TextColumn::make('quantity_display')
                    ->label('Quantity')
                    ->state(fn ($record) => $record->entry_type === ProductionJournalEntryType::Output ? $record->output_quantity : $record->quantity)
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right'),

                TextColumn::make('unit_of_measure_code')->label('UOM'),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money()
                    ->alignment('right'),

                TextColumn::make('line_status')
                    ->label('Status')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add Production Line'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('line_no', 'asc');
    }
}
