<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

use App\Enums\ProductionOrderSourceType;
use App\Enums\ProductionOrderStatus;
use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Item;
use App\Models\Manufacturing\ProductionBomVersion;
use App\Models\Manufacturing\RoutingVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductionOrderForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Primary document identification and status.')
                    ->icon('heroicon-m-document-text')
                    ->columns(3)
                    ->schema([
                        static::makeSystemGeneratedTextInput(
                            'document_number',
                            'Order Number',
                            'Generated automatically when the production order is created and cannot be changed.',
                            'Auto-generated'
                        ),

                        Select::make('status')
                            ->options(ProductionOrderStatus::class)
                            ->default(ProductionOrderStatus::SIMULATED)
                            ->required()
                            ->native(false),

                        Select::make('source_type')
                            ->options(ProductionOrderSourceType::class)
                            ->default(ProductionOrderSourceType::ITEM)
                            ->required()
                            ->live(),

                        TextInput::make('source_no')
                            ->label('Source No.')
                            ->maxLength(50),

                        Select::make('capex_project_id')
                            ->label('CapEx Project')
                            ->relationship('capexProject', 'project_number')
                            ->searchable()
                            ->preload()
                            ->placeholder('None (Operational)'),

                        Select::make('item_id')
                            ->label('Item')
                            ->relationship('item', 'item_code', fn ($query) => $query->finishedGoods()->where('blocked', false))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->columnSpan(2)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) {
                                    return;
                                }

                                $item = Item::find($state);
                                if ($item) {
                                    $set('description', $item->description);
                                    $set('unit_of_measure_code', $item->base_unit_of_measure);
                                    $set('conversion_factor', 1.0); // Reset to base
                                    $set('production_bom_id', $item->production_bom_id);
                                    $set('routing_id', $item->routing_id);
                                    $set('inventory_posting_group_id', $item->inventory_posting_group_id);
                                    $set('general_product_posting_group_id', $item->general_product_posting_group_id);
                                    $set('flushing_method', $item->flushing_method ?? 'MANUAL');
                                    $set('costing_method', $item->costing_method ?? 'STANDARD');
                                    $set('unit_cost', $item->unit_cost ?? 0);

                                    // Recalculate base quantity
                                    $qty = (float) ($get('quantity') ?? 1);
                                    $set('quantity_base', $qty * 1.0);
                                }
                            }),

                        TextInput::make('description')
                            ->required()
                            ->columnSpanFull()
                            ->maxLength(255),
                    ]),

                Section::make('Quantities & Unit of Measure')
                    ->icon('heroicon-m-beaker')
                    ->columns(3)
                    ->schema([
                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $factor = (float) ($get('conversion_factor') ?? 1);
                                $set('quantity_base', (float) $state * $factor);
                            }),

                        Select::make('unit_of_measure_code')
                            ->label('UOM')
                            ->options(function (Get $get) {
                                $itemId = $get('item_id');
                                if (! $itemId) {
                                    return [];
                                }

                                $item = Item::find($itemId);
                                if (! $item) {
                                    return [];
                                }

                                $uoms = $item->uoms()
                                    ->get()
                                    ->mapWithKeys(fn ($uom) => [
                                        $uom->uom_code => $uom->uom_code.($uom->description ? " ({$uom->description})" : ''),
                                    ]);

                                // BCR Fallback: If no pivot entries, always allow the base_unit_of_measure
                                if ($uoms->isEmpty() && $item->base_unit_of_measure) {
                                    return [$item->base_unit_of_measure => $item->base_unit_of_measure];
                                }

                                return $uoms->toArray();
                            })
                            ->live()
                            ->required()
                            ->searchable()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $itemId = $get('item_id');
                                if (! $itemId || ! $state) {
                                    return;
                                }

                                $item = Item::find($itemId);
                                if (! $item) {
                                    return;
                                }

                                // Fetch conversion factor from pivot
                                $uom = $item->uoms()->where('uom_code', $state)->first();
                                $factor = (float) ($uom?->pivot?->conversion_factor ?? 1.0);

                                $set('conversion_factor', $factor);

                                $qty = (float) ($get('quantity') ?? 0);
                                $set('quantity_base', $qty * $factor);
                            }),

                        TextInput::make('conversion_factor')
                            ->numeric()
                            ->hidden()
                            ->default(1.0)
                            ->dehydrated(),

                        TextInput::make('quantity_base')
                            ->label('Base Quantity')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Section::make('BOM & Routing Configuration')
                    ->description('Technical structure and versioning.')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->columns(2)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('production_bom_id')
                                ->label('Production BOM')
                                ->relationship('productionBom', 'description')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Select::make('production_bom_version_id')
                                ->label('BOM Version')
                                ->placeholder('Latest Active')
                                ->options(function (Get $get) {
                                    $bomId = $get('production_bom_id');
                                    if (! $bomId) {
                                        return [];
                                    }

                                    return ProductionBomVersion::where('production_bom_id', $bomId)
                                        ->pluck('version_code', 'id');
                                })
                                ->live(),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('routing_id')
                                ->label('Routing')
                                ->relationship('routing', 'description')
                                ->searchable()
                                ->preload()
                                ->live(),

                            Select::make('routing_version_id')
                                ->label('Routing Version')
                                ->relationship('routingVersion', 'version_code')
                                ->placeholder('Latest Active')
                                ->options(function (Get $get) {
                                    $routingId = $get('routing_id');
                                    if (! $routingId) {
                                        return [];
                                    }

                                    return RoutingVersion::where('routing_id', $routingId)
                                        ->pluck('version_code', 'id');
                                })
                                ->live(),
                        ]),

                        Select::make('flushing_method')
                            ->options([
                                'MANUAL' => 'Manual',
                                'FORWARD' => 'Forward',
                                'BACKWARD' => 'Backward',
                                'PICK + BACKWARD' => 'Pick + Backward',
                                'PICK + FORWARD' => 'Pick + Forward',
                            ])
                            ->required(),

                        TextInput::make('scrap_percent')
                            ->label('Scrap %')
                            ->numeric()
                            ->suffix('%')
                            ->default(0),
                    ]),

                Section::make('Scheduling & Warehouse')
                    ->icon('heroicon-m-calendar-days')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('due_date')
                            ->required()
                            ->native(false)
                            ->default(now()->addDays(7)),

                        Grid::make(2)->schema([
                            DateTimePicker::make('starting_date_time')
                                ->label('Starting')
                                ->native(false),
                            DateTimePicker::make('ending_date_time')
                                ->label('Ending')
                                ->native(false),
                        ]),

                        Select::make('location_code')
                            ->label('Target Location')
                            ->relationship('location', 'code')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('bin_code')
                            ->label('Target Bin'),
                    ]),

                Section::make('Costing & Posting')
                    ->icon('heroicon-m-banknotes')
                    ->columns(2)
                    ->schema([
                        Select::make('costing_method')
                            ->options([
                                'STANDARD' => 'Standard',
                                'FIFO' => 'FIFO',
                                'LIFO' => 'LIFO',
                                'AVERAGE' => 'Average',
                                'SPECIFIC' => 'Specific',
                            ])
                            ->required(),

                        TextInput::make('unit_cost')
                            ->numeric()
                            ->prefix('₦')
                            ->step(0.0001)
                            ->label('Standard Cost')
                            ->helperText('Expected unit cost for the finished item.'),

                        TextInput::make('cost_rollup')
                            ->numeric()
                            ->prefix('₦')
                            ->step(0.0001)
                            ->label('Rolled-up Cost')
                            ->disabled()
                            ->helperText('Sum of component and capacity costs from the BOM and Routing.'),

                        Select::make('inventory_posting_group_id')
                            ->label('Inventory Posting Group')
                            ->relationship('inventoryPostingGroup', 'code')
                            ->required(),

                        Select::make('general_product_posting_group_id')
                            ->label('Gen. Product Posting Group')
                            ->relationship('generalProductPostingGroup', 'code')
                            ->required(),
                    ]),

                Section::make('Dimensions & Planning')
                    ->icon('heroicon-m-tag')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('shortcut_dimension_1_code')->label('Department'),
                        TextInput::make('shortcut_dimension_2_code')->label('Project'),
                        TextInput::make('priority')->numeric()->default(100),
                        Toggle::make('reserved_from_stock')
                            ->label('Reserve from Stock'),
                    ]),
            ]);
    }
}
