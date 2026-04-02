<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

use App\Enums\ProductionOrderStatus;
use App\Models\Item;
use App\Models\Manufacturing\ProductionOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductionOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                    Section::make('Production Order Information')
                        ->columns(2)
                        ->schema([
                            TextInput::make('document_number')
                                ->default(fn () => ProductionOrder::generateDocumentNumber())
                                ->disabled()
                                ->dehydrated(),

                            Select::make('status')
                                ->enum(ProductionOrderStatus::class)
                                ->options(ProductionOrderStatus::class)
                                ->default(ProductionOrderStatus::SIMULATED)
                                ->required()
                                ->disabled(fn ($record) => $record?->status === ProductionOrderStatus::FINISHED),

                            Select::make('item_id')
                                ->relationship('item', 'description')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    $item = \App\Models\Item::find($state);
                                    if ($item) {
                                        $set('unit_of_measure_code', $item->base_unit_of_measure);
                                        $set('production_bom_id', $item->production_bom_id);
                                        $set('routing_id', $item->routing_id);
                                    }
                                }),

                            TextInput::make('description')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $set('quantity_base', $state)),

                            Select::make('unit_of_measure_code')
                                ->options(function (Get $get) {
                                    $itemId = $get('item_id');
                                    if (!$itemId) return [];

                                    // Load UOMs from pivot table
                                    return Item::find($itemId)
                                        ?->uoms()
                                        ?->get()
                                        ?->mapWithKeys(fn ($uom) => [
                                            $uom->code => sprintf(
                                                '%s (%s) - %s : 1 %s',
                                                $uom->code,
                                                $uom->description,
                                                $uom->pivot->conversion_factor,
                                                $uom->pivot->uom_type
                                            )
                                        ])
                                        ?->toArray() ?? [];
                                })
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    $itemId = $get('item_id');
                                    if (!$itemId || !$state) return;

                                    // Get conversion factor for selected UOM
                                    $uom = Item::find($itemId)
                                        ->unitOfMeasures()
                                        ->where('code', $state)
                                        ->first();

                                    if ($uom) {
                                        $set('conversion_factor', $uom->pivot->conversion_factor);
                                        $set('uom_type', $uom->pivot->uom_type);

                                        // Recalculate quantity_base if quantity exists
                                        $qty = $get('quantity');
                                        if ($qty) {
                                            $set('quantity_base', $qty * $uom->pivot->conversion_factor);
                                        }
                                    }
                                }),


                            DatePicker::make('due_date')
                                ->required()
                                ->default(now()->addDays(7)),

                            Select::make('location_code')
                                ->options([
                                    'RAW-WAREHOUSE' => 'Raw Materials Warehouse',
                                    'PROD-FLOOR' => 'Production Floor',
                                    'FG-WAREHOUSE-A' => 'Finished Goods A',
                                ])
                                ->default('PROD-FLOOR')
                                ->required(),
                        ]),

                    Section::make('BOM & Routing')
                        ->columns(2)
                        ->schema([
                            Select::make('production_bom_id')
                                ->relationship('productionBom', 'description')
                                ->searchable()
                                ->preload(),

                            Select::make('routing_id')
                                ->relationship('routing', 'description')
                                ->searchable()
                                ->preload(),

                            Select::make('costing_method')
                                ->options([
                                    'STANDARD' => 'Standard',
                                    'FIFO' => 'FIFO',
                                    'AVERAGE' => 'Average',
                                    'ACTUAL' => 'Actual',
                                ])
                                ->default('STANDARD')
                                ->required(),

                            TextInput::make('unit_cost')
                                ->numeric()
                                ->prefix('$')
                                ->default(0),
                        ]),

                    Section::make('Dimensions')
                        ->columns(2)
                        ->schema([
                           TextInput::make('shortcut_dimension_1_code')
                                ->label('Department'),
                            TextInput::make('shortcut_dimension_2_code')
                                ->label('Project'),
                        ]),
                ]);
    }
}
