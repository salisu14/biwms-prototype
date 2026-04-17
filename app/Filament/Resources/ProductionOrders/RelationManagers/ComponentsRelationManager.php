<?php

namespace App\Filament\Resources\ProductionOrders\RelationManagers;

use App\Models\Item;
use App\Services\Manufacturing\ProductionOrderService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $title = 'Material BOM (Components)';

    // This icon helps distinguish it from the "Lines" manager in the tabs
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    // Link to specific Order Line if applicable
                    Select::make('production_order_line_id')
                        ->label('Related Order Line')
                        ->placeholder('Optional: Link to a specific output line')
                        ->options(fn ($livewire) => $livewire->ownerRecord->lines->pluck('description', 'id')
                        )
                        ->columnSpan(2),

                    TextInput::make('line_number')
                        ->numeric()
                        ->default(fn ($livewire) => ($livewire->ownerRecord->components()->max('line_number') ?? 0) + 10000)
                        ->columnSpan(1),
                ]),

                Section::make('Item Details')
                    ->columns(2)
                    ->schema([
                        Select::make('item_id')
                            ->relationship('item', 'description')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $item = Item::find($state);
                                    $set('description', $item?->description);
                                    $set('unit_of_measure_code', $item?->base_unit_of_measure);
                                    $set('unit_cost', $item?->unit_cost);
                                }
                            }),

                        TextInput::make('description')
                            ->required(),

                        Select::make('unit_of_measure_code')
                            ->label('UOM')
                            ->relationship('unitOfMeasure', 'uom_code')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('location_code')
                            ->label('Location')
                            ->relationship('location', 'code')
                            ->searchable()
                            ->preload(),

                        TextInput::make('routing_link_code')
                            ->label('Routing Link')
                            ->placeholder('e.g. ASSEMBLY, PACKING'),
                    ]),

                Section::make('Quantities')
                    ->columns(3)
                    ->schema([
                        TextInput::make('quantity_per')
                            ->label('Qty Per Parent')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set, $livewire) => $set('expected_quantity', $state * ($livewire->ownerRecord->quantity ?? 1))
                            ),

                        TextInput::make('expected_quantity')
                            ->numeric()
                            ->required(),

                        TextInput::make('scrap_percent')
                            ->label('Scrap %')
                            ->numeric()
                            ->suffix('%'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_number')
                    ->label('Seq')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('item.item_code')
                    ->label('Item No')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('expected_quantity')
                    ->label('Expected')
                    ->numeric(2)
                    ->summarize(Sum::make()->label('Total Expected')),

                TextColumn::make('actual_quantity_consumed')
                    ->label('Consumed')
                    ->numeric(2)
                    ->color(fn ($state, $record) => $state >= $record->expected_quantity ? 'success' : 'gray'),

                TextColumn::make('remaining_quantity')
                    ->label('Remaining')
                    ->numeric(2)
//                    ->color('danger')
                    ->color(fn ($state, $record) => ($state ?? 0) >= ($record?->expected_quantity ?? 0) ? 'success' : 'gray')
                    ->hidden(fn ($record) => ($record?->remaining_quantity ?? 0) <= 0),
                //                    ->hidden(fn ($record) => $record->remaining_quantity <= 0),

                TextColumn::make('location_code')
                    ->label('Warehouse')
                    ->badge(),
            ])
            ->filters([
                // Filter components by the specific Output Line
                SelectFilter::make('production_order_line_id')
                    ->label('By Output Line')
                    ->options(fn ($livewire) => $livewire->ownerRecord->lines->pluck('description', 'id')
                    ),

                TernaryFilter::make('has_shortage')
                    ->placeholder('All Items')
                    ->trueLabel('Shortages (Remaining > 0)')
                    ->queries(
                        true: fn ($query) => $query->whereRaw('expected_quantity > actual_quantity_consumed'),
                        false: fn ($query) => $query,
                    ),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    // Custom action to post consumption
                    Action::make('consume')
                        ->icon('heroicon-o-beaker')
                        ->color('success')
                        ->schema([
                            TextInput::make('amount')
                                ->numeric()
                                ->required()
                                ->default(fn ($record) => $record->expected_quantity - $record->actual_quantity_consumed),
                        ])
                        ->action(function ($record, array $data, $livewire) {
                            app(ProductionOrderService::class)->postConsumption(
                                $livewire->getOwnerRecord(),
                                [[
                                    'component_id' => $record->id,
                                    'quantity' => $data['amount'],
                                    'scrap_quantity' => 0,
                                ]],
                                auth()->id()
                            );
                        }),
                ]),
            ]);
    }
}
