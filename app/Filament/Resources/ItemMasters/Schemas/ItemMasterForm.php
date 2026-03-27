<?php

namespace App\Filament\Resources\ItemMasters\Schemas;

use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use App\Enums\UomType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemMasterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Basic Information
                TextInput::make('item_code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),

                Select::make('item_type')
                    ->options(ItemType::options())
                    ->default(ItemType::RAW_MATERIAL->value)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $type = ItemType::tryFrom($state);
                        if ($type === ItemType::SERVICE) {
                            $set('inventory_method', null);
                        }
                    }),

                Select::make('inventory_method')
                    ->options(InventoryMethod::options())
                    ->default(InventoryMethod::FIFO->value)
                    ->required()
                    ->visible(fn ($get) => $get('item_type') !== ItemType::SERVICE->value)
                    ->helperText(fn ($state) => $state ? InventoryMethod::tryFrom($state)?->description() : null),

                TextInput::make('description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                // Unit of Measures Section (M2M with pivot)
                Section::make('Unit of Measures')
                    ->description('Define all UOMs with conversion factors to base')
                    ->schema([
                        Repeater::make('uomAssignments')
                            ->relationship('uomAssignments')
                            ->schema([
                                Select::make('uom_id')
                                    ->label('Unit of Measure')
                                    ->options(fn () => \App\Models\UnitOfMeasure::pluck('uom_code', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('uom_type')
                                    ->label('UOM Type')
                                    ->options(UomType::options())
                                    ->required(),

                                TextInput::make('conversion_factor')
                                    ->label('Conversion to Base')
                                    ->numeric()
                                    ->default(1.0)
                                    ->required()
                                    ->step(0.000001)
                                    ->helperText('How many of this UOM = 1 Base UOM'),

                                Toggle::make('is_default')
                                    ->label('Default for Type')
                                    ->default(false)
                                    ->helperText('Only one default per type allowed'),

                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->hidden(),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->addActionLabel('Add UOM')
                            ->reorderableWithDragAndDrop()
                            ->reorderable('sort_order')
                            ->itemLabel(fn (array $state): ?string =>
                            isset($state['uom_type'])
                                ? UomType::tryFrom($state['uom_type'])?->label()
                                : null
                            ),
                    ]),

                // Reference Pricing (not vendor-specific)
                Section::make('Reference Pricing')
                    ->description('Standard reference prices. Actual vendor prices managed in Vendor Items.')
                    ->schema([
                        TextInput::make('reference_cost')
                            ->label('Reference Unit Cost')
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step(0.0001)
                            ->minValue(0)
                            ->helperText('Reference only - actual cost per vendor in Vendor Items'),

                        TextInput::make('reference_price')
                            ->label('Reference Selling Price')
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step(0.0001)
                            ->minValue(0)
                            ->helperText('Suggested selling price'),
                    ])
                    ->columns(2),

                // Vendor Items Section (M2M with pivot data)
                Section::make('Vendor Items')
                    ->description('Vendors who supply this item with their specific pricing and terms')
                    ->schema([
                        Repeater::make('vendorItems')
                            ->relationship('vendorItems')
                            ->schema([
                                Select::make('vendor_id')
                                    ->label('Vendor')
                                    ->options(fn () => \App\Models\Vendor::pluck('vendor_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('vendor_item_number')
                                    ->label('Vendor Item Number')
                                    ->required()
                                    ->maxLength(50)
                                    ->placeholder('Vendor\'s SKU'),

                                TextInput::make('vendor_item_name')
                                    ->label('Vendor Item Name')
                                    ->maxLength(255)
                                    ->placeholder('Vendor\'s description (optional)'),

                                TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->required()
                                    ->prefix('$')
                                    ->step(0.0001)
                                    ->minValue(0),

                                TextInput::make('currency')
                                    ->label('Currency')
                                    ->default('USD')
                                    ->maxLength(3)
                                    ->required(),

                                TextInput::make('minimum_order_qty')
                                    ->label('Minimum Order Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->step(0.0001)
                                    ->minValue(0),

                                TextInput::make('lead_time_days')
                                    ->label('Lead Time (Days)')
                                    ->numeric()
                                    ->integer()
                                    ->default(0)
                                    ->minValue(0),

                                Toggle::make('is_preferred')
                                    ->label('Preferred Vendor')
                                    ->default(false)
                                    ->helperText('Check if this is the preferred vendor for this item'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Section::make('Price Breaks (Quantity Discounts)')
                                    ->schema([
                                        Repeater::make('price_breaks')
                                            ->schema([
                                                TextInput::make('quantity')
                                                    ->numeric()
                                                    ->required()
                                                    ->label('Min Quantity'),
                                                TextInput::make('price')
                                                    ->numeric()
                                                    ->required()
                                                    ->prefix('$')
                                                    ->label('Unit Price'),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('Add Price Break')
                                            ->defaultItems(0),
                                    ])
                                    ->collapsible(),

                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add Vendor')
                            ->itemLabel(fn (array $state): ?string =>
                            isset($state['vendor_id'])
                                ? \App\Models\Vendor::find($state['vendor_id'])?->vendor_name
                                : null
                            ),
                    ]),

                // Categories (M2M)
                Section::make('Classification')
                    ->schema([
                        Select::make('categories')
                            ->label('Categories')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->options(fn () => \App\Models\Category::pluck('category_name', 'id'))
                            ->dehydrateStateUsing(fn ($state) => $state ?? []),
                    ]),

                // Other fields
                Section::make('Additional Information')
                    ->schema([
                        TextInput::make('shelf_life_days')
                            ->label('Shelf Life (Days)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->placeholder('Leave empty if not applicable'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->required()
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
