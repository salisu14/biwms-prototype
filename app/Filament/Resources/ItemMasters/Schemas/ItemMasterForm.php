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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ItemMasterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Item Master')
                    ->tabs([
                        // TAB 1: Basic Information
                        Tabs\Tab::make('Basic')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Section::make('Identification')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('item_code')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(50)
                                            ->placeholder('e.g., RM-001'),

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

                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->inline(false),
                                    ]),

                                Section::make('Description')
                                    ->schema([
                                        TextInput::make('description')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull()
                                            ->placeholder('Enter item description'),
                                    ]),
                            ]),

                        // TAB 2: Classification & 3NF Setup
                        Tabs\Tab::make('Classification & Posting')
                            ->icon('heroicon-m-tag')
                            ->schema([
                                // FIXED: Categories with pivot fields
                                Section::make('Categories')
                                    ->description('Assign categories with primary designation')
                                    ->schema([
                                        Repeater::make('categoryAssignments')
                                            ->relationship('categoryAssignments') // FIXED: Use pivot relationship
                                            ->schema([
                                                Select::make('category_id')
                                                    ->label('Category')
                                                    ->options(fn () => \App\Models\Category::pluck('category_name', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(),

                                                Toggle::make('is_primary')
                                                    ->label('Primary Category')
                                                    ->default(false)
                                                    ->helperText('Only one primary category allowed per item'),

                                                TextInput::make('sort_order')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->hidden(),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(1)
                                            ->addActionLabel('Add Category')
                                            ->reorderableWithDragAndDrop()
                                            ->reorderable('sort_order')
                                            ->itemLabel(fn (array $state): ?string =>
                                            isset($state['category_id'])
                                                ? \App\Models\Category::find($state['category_id'])?->category_name
                                                : null
                                            ),
                                    ]),

                                // FIXED: 3NF Posting Setup Fields
                                Section::make('Posting Setup (3NF)')
                                    ->description('VAT and GL account assignments')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('vat_id')
                                            ->label('VAT Code')
                                            ->options(fn () => \App\Models\VatMaster::pluck('code', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select VAT code')
                                            ->helperText('Default VAT for this item'),

                                        Select::make('general_posting_setup_id')
                                            ->label('General Posting Setup')
                                            ->options(fn () => \App\Models\GeneralPostingSetup::pluck('description', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select GL posting setup')
                                            ->helperText('GL accounts for sales/purchase'),

                                        Select::make('inventory_posting_setup_id')
                                            ->label('Inventory Posting Setup')
                                            ->options(fn () => \App\Models\InventoryPostingSetup::pluck('description', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select inventory posting setup')
                                            ->helperText('GL accounts for inventory transactions'),
                                    ]),
                            ]),

                        // TAB 3: Unit of Measures
                        Tabs\Tab::make('UOMs')
                            ->icon('heroicon-m-scale')
                            ->schema([
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
                            ]),

                        // TAB 4: Pricing
                        Tabs\Tab::make('Pricing')
                            ->icon('heroicon-m-currency-dollar')
                            ->schema([
                                Section::make('Reference Pricing')
                                    ->description('Standard reference prices. Actual vendor prices managed in Vendor Items.')
                                    ->columns(2)
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
                                    ]),

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

                                                // FIXED: Price breaks as JSON field (not nested repeater)
                                                Textarea::make('price_breaks')
                                                    ->label('Price Breaks (JSON)')
                                                    ->placeholder('e.g., [{"quantity": 100, "price": 9.50}, {"quantity": 500, "price": 8.75}]')
                                                    ->helperText('Enter as JSON array: [{"quantity": QTY, "price": PRICE}, ...]')
                                                    ->rows(3)
                                                    ->columnSpanFull()
                                                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                                                    ->dehydrateStateUsing(function ($state) {
                                                        if (is_array($state)) return $state;
                                                        try {
                                                            return json_decode($state, true) ?? [];
                                                        } catch (\Exception $e) {
                                                            return [];
                                                        }
                                                    }),

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
                            ]),

                        // TAB 5: Additional
                        Tabs\Tab::make('Additional')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->schema([
                                Section::make('Other Information')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('shelf_life_days')
                                            ->label('Shelf Life (Days)')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->placeholder('Leave empty if not applicable'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
