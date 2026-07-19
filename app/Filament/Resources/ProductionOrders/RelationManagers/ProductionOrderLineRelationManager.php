<?php

namespace App\Filament\Resources\ProductionOrders\RelationManagers;

use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Models\Item;
use App\Services\Manufacturing\ProductionOrderService;
use App\Support\DecimalFormatter;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductionOrderLineRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $title = 'Product Order Lines';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Item Selection')
                    ->columns(2)
                    ->schema([
                        Select::make('item_id')
                            ->label('Finished Good')
                            ->relationship('item', 'description')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                if (! $state || filled($get('unit_cost'))) {
                                    return;
                                }

                                $this->fillItemDefaults((int) $state, $set, $get);
                            })
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) {
                                    return;
                                }

                                $this->fillItemDefaults((int) $state, $set, $get);
                            }),

                        TextInput::make('description')
                            ->required()
                            ->maxLength(255),

                        Select::make('production_bom_id')
                            ->label('Production BOM')
                            ->relationship('productionBom', 'description')
                            ->searchable(),

                        Select::make('routing_id')
                            ->label('Routing')
                            ->relationship('routing', 'description')
                            ->searchable(),
                    ]),

                Grid::make(3)
                    ->schema([
                        TextInput::make('quantity')
                            ->numeric()
                            ->step('0.00000001')
                            ->default(1)
                            ->required()
                            ->live()
                            ->formatStateUsing(fn ($state): string => DecimalFormatter::quantityForInput($state))
                            ->dehydrateStateUsing(fn ($state): string => DecimalMath::quantity($state))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('cost_amount', DecimalMath::amount(DecimalMath::mul($state ?? 0, $get('unit_cost') ?? 0, DecimalPrecision::AMOUNT_SCALE)))
                            ),

                        Select::make('unit_of_measure_code')
                            ->label('UOM')
                            ->relationship('unitOfMeasure', 'uom_code')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('unit_cost')
                            ->numeric()
                            ->step('0.00000001')
                            ->prefix('₦')
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('cost_amount', DecimalMath::amount(DecimalMath::mul($state ?? 0, $get('quantity') ?? 0, DecimalPrecision::AMOUNT_SCALE)))
                            ),

                        TextInput::make('cost_amount')
                            ->label('Total Cost')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Section::make('Scheduling & Warehouse')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('due_date')
                            ->default(now()->addDays(7))
                            ->required(),

                        DateTimePicker::make('starting_date_time')
                            ->label('Start Date/Time'),

                        DateTimePicker::make('ending_date_time')
                            ->label('End Date/Time'),

                        Select::make('location_code')
                            ->label('Location')
                            ->relationship('location', 'code')
                            ->searchable()
                            ->preload(),

                        TextInput::make('bin_code')
                            ->label('Bin'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                IconColumn::make('finished')
                    ->boolean()
                    ->label('Status')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),

                TextColumn::make('item.item_code')
                    ->label('Item No')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('description')
                    ->weight(FontWeight::Bold)
                    ->description(fn ($record) => 'BOM: '.($record->productionBom?->description ?? 'None'))
                    ->searchable(),

                TextColumn::make('quantity')
                    ->numeric(2)
                    ->alignment(Alignment::End)
                    ->suffix(fn ($record): string => $record->unit_of_measure_code ? " {$record->unit_of_measure_code}" : ''),

                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->due_date->isPast() && ! $record->finished ? 'danger' : 'gray'),

                TextColumn::make('location_code')
                    ->label('Location')
                    ->badge(),

                TextColumn::make('cost_amount')
                    ->money('NGN')
                    ->summarize(Sum::make()->label('Total Cost')),
            ])
            ->filters([
                TernaryFilter::make('finished')
                    ->label('Completion Status'),

                SelectFilter::make('item_id')
                    ->relationship('item', 'description')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data = $this->mergeItemDefaults($data);
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->mutateDataUsing(function (array $data): array {
                            $data = $this->mergeItemDefaults($data);
                            $data['last_modified_by'] = auth()->id();

                            return $data;
                        }),

                    // Standard BC Post Output action
                    Action::make('post_output')
                        ->label('Post Output')
                        ->icon('heroicon-m-archive-box')
                        ->color('success')
                        ->visible(fn ($record) => (auth()->user()?->can('postOutput', $record->productionOrder) ?? false) && (float) ($record->productionOrder?->remaining_quantity ?? 0) > 0)
                        ->schema([
                            TextInput::make('quantity')
                                ->numeric()
                                ->step('0.00000001')
                                ->minValue('0.00000001')
                                ->required()
                                ->default(fn ($record): float => ProductionOrderActions::postOutputDefaultQuantity($record->productionOrder))
                                ->helperText(fn ($record): string => ProductionOrderActions::postOutputHelperText($record->productionOrder))
                                ->rules([
                                    fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record): void {
                                        $productionOrder = $record->productionOrder;
                                        $quantityInOrderUom = (float) $value;
                                        $quantityBase = ProductionOrderActions::convertOrderUomToBase($productionOrder, $quantityInOrderUom);

                                        if ($quantityBase > (float) $productionOrder->remaining_quantity) {
                                            $fail('Cannot post more than the remaining production output.');
                                        }
                                    },
                                ]),
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                $productionOrder = $record->productionOrder;
                                $quantityInOrderUom = (float) $data['quantity'];
                                $quantityBase = ProductionOrderActions::convertOrderUomToBase($productionOrder, $quantityInOrderUom);

                                app(ProductionOrderService::class)->postOutput($productionOrder, $quantityBase, auth()->id());
                                Notification::make()
                                    ->title('Output successfully posted')
                                    ->success()
                                    ->send();
                            } catch (\Exception $exception) {
                                Notification::make()
                                    ->title('Error')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function fillItemDefaults(int $itemId, Set $set, Get $get): void
    {
        $item = Item::find($itemId);

        if (! $item) {
            return;
        }

        if (blank($get('description'))) {
            $set('description', $item->description);
        }

        if (blank($get('unit_of_measure_code'))) {
            $set('unit_of_measure_code', $item->base_unit_of_measure);
        }

        $set('unit_cost', DecimalMath::unitCost($item->unit_cost ?? 0));

        if (blank($get('production_bom_id')) && filled($item->production_bom_id)) {
            $set('production_bom_id', $item->production_bom_id);
        }

        if (blank($get('routing_id')) && filled($item->routing_id)) {
            $set('routing_id', $item->routing_id);
        }

        $set('cost_amount', DecimalMath::amount(DecimalMath::mul($get('quantity') ?? 0, $item->unit_cost ?? 0, DecimalPrecision::AMOUNT_SCALE)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeItemDefaults(array $data): array
    {
        $itemId = (int) ($data['item_id'] ?? 0);

        if ($itemId <= 0) {
            return $data;
        }

        $item = Item::find($itemId);

        if (! $item) {
            return $data;
        }

        $data['description'] = $data['description'] ?: $item->description;
        $data['unit_of_measure_code'] = $data['unit_of_measure_code'] ?: $item->base_unit_of_measure;
        $data['unit_cost'] = filled($data['unit_cost'] ?? null) ? $data['unit_cost'] : DecimalMath::unitCost($item->unit_cost ?? 0);
        $data['production_bom_id'] = $data['production_bom_id'] ?: $item->production_bom_id;
        $data['routing_id'] = $data['routing_id'] ?: $item->routing_id;
        $data['cost_amount'] = DecimalMath::amount(DecimalMath::mul($data['quantity'] ?? 0, $data['unit_cost'] ?? 0, DecimalPrecision::AMOUNT_SCALE));

        return $data;
    }
}
