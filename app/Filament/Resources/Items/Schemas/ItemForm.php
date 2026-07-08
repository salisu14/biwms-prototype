<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\CostingMethod;
use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use App\Enums\PriceCalculationMethod;
use App\Enums\UomType;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Item;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\Routing;
use App\Models\UnitOfMeasure;
use App\Models\Vendor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ItemForm
{
    private static function isFinishedGood(mixed $itemType): bool
    {
        if ($itemType instanceof ItemType) {
            return $itemType === ItemType::FINISHED_GOOD;
        }

        return (string) $itemType === ItemType::FINISHED_GOOD->value;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Item Management')
                    ->tabs([
                        // --- GENERAL TAB ---
                        Tabs\Tab::make('General')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                ])->schema([
                                    TextInput::make('item_code')
                                        ->label('Item Code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->placeholder('e.g., ITEM-001')
                                        ->prefixIcon('heroicon-o-qr-code')
                                        ->maxLength(20)
                                        // Lock the field if the record already exists in the database
                                        ->disabled(fn (?Item $record) => $record !== null)
                                        // Ensure the value is still sent to the database during creation
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The code cannot be changed once the Item is created.'),

                                    Select::make('item_type')
                                        ->options(ItemType::class)
                                        ->required()
                                        ->default(ItemType::FINISHED_GOOD)
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set) {
                                            if (! self::isFinishedGood($state)) {
                                                $set('production_bom_id', null);
                                                $set('routing_id', null);
                                            }
                                        }),

                                    TextInput::make('sku')
                                        ->label('Base SKU')
                                        ->maxLength(20)
                                        ->placeholder('Base/Global SKU'),

                                    Select::make('item_category_id')
                                        ->label('Primary Category')
                                        ->relationship('primaryCategory', 'category_name')
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('description')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpanFull()
                                        ->placeholder('Primary item description'),

                                    Textarea::make('description_2')
                                        ->label('Extended Description')
                                        ->placeholder('Additional details, specifications, or notes...')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),

                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'xl' => 3,
                                ])->schema([
                                    Select::make('inventory_method')
                                        ->label('Inventory Method')
                                        ->options(InventoryMethod::class)
                                        ->required()
                                        ->default(InventoryMethod::FIFO)
                                        ->native(false)
                                        ->visible(fn ($get) => $get('item_type') !== ItemType::SERVICE->value),

                                    // Added: Missing costing_method from model
                                    Select::make('costing_method')
                                        ->label('Costing Method')
                                        ->options(CostingMethod::class)
                                        ->required()
                                        ->default(CostingMethod::FIFO)
                                        ->native(false),

                                    // Added: Missing uom_id (Base Unit of Measure) relationship
                                    // In ItemForm.php, General tab, replace the uom_id field:

                                    Select::make('base_uom_id')
                                        ->label('Base Unit of Measure')
                                        ->relationship('baseUom', 'uom_code')
                                        ->required()
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('shelf_life_days')
                                        ->label('Shelf Life (Days)')
                                        ->numeric()
                                        ->integer()
                                        ->placeholder('Leave blank if non-perishable'),

                                    Select::make('production_bom_id')
                                        ->label('Production BOM')
                                        ->options(fn () => ProductionBom::query()
                                            ->whereIn('status', ['UNDER_DEVELOPMENT', 'CERTIFIED'])
                                            ->orderBy('code')
                                            ->pluck('code', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->visible(fn ($get) => self::isFinishedGood($get('item_type')))
                                        ->dehydrated(fn ($get) => self::isFinishedGood($get('item_type')))
                                        ->helperText('Optional. Only for production items.'),

                                    Select::make('routing_id')
                                        ->label('Routing')
                                        ->options(fn () => Routing::query()
                                            ->whereIn('status', ['DRAFT', 'CERTIFIED'])
                                            ->orderBy('code')
                                            ->pluck('code', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->visible(fn ($get) => self::isFinishedGood($get('item_type')))
                                        ->dehydrated(fn ($get) => self::isFinishedGood($get('item_type')))
                                        ->helperText('Optional. Only for production items.'),
                                ]),
                            ]),

                        // --- PRICING & COSTING TAB ---
                        Tabs\Tab::make('Pricing & Costing')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'xl' => 3,
                                ])->schema([
                                    TextInput::make('unit_price')
                                        ->label('Sales Price (Base)')
                                        ->numeric()
                                        ->prefix('$')
                                        ->required()
                                        ->step(0.0001),

                                    TextInput::make('unit_cost')
                                        ->label('Unit Cost (Avg/LIFO)')
                                        ->numeric()
                                        ->prefix('$')
                                        ->required()
                                        ->step(0.0001),

                                    TextInput::make('standard_cost')
                                        ->label('Standard Cost (Fixed)')
                                        ->required()
                                        ->numeric()
                                        ->prefix('$')
                                        ->step(0.0001),

                                    // Added: Missing last_direct_cost from model
                                    TextInput::make('last_direct_cost')
                                        ->label('Last Direct Cost')
                                        ->numeric()
                                        ->prefix('$')
                                        ->readOnly()
                                        ->helperText('Last purchase price from vendor.'),

                                    TextInput::make('profit_percent')
                                        ->label('Profit Margin %')
                                        ->numeric()
                                        ->suffix('%')
                                        ->step(0.01),

                                    Select::make('price_calculation_method')
                                        ->options(PriceCalculationMethod::class)
                                        ->default(PriceCalculationMethod::STANDARD)
                                        ->native(false),

                                    Select::make('currency_id')
                                        ->label('Default Currency')
                                        ->relationship('currency', 'code')
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('default_price_list_code')
                                        ->label('Price List Ref')
                                        ->placeholder('Internal price list mapping'),
                                ]),

                                Toggle::make('allow_negative_price')
                                    ->label('Allow Negative Pricing')
                                    ->helperText('Used for promotional or discount items.')
                                    ->default(false),
                            ]),

                        // --- RELATIONSHIPS TAB (UOM, CATEGORY, VENDOR) ---
                        Tabs\Tab::make('Relationships')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Section::make('Category Classifications')
                                    ->collapsible()
                                    ->schema([
                                        Repeater::make('categoryAssignments')
                                            ->relationship('categoryAssignments')
                                            ->schema([
                                                Select::make('category_id')
                                                    ->label('Category')
                                                    ->relationship('category', 'category_name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(),
                                                Toggle::make('is_primary')
                                                    ->label('Primary')
                                                    ->inline(false),
                                            ])
                                            ->columns([
                                                'default' => 1,
                                                'md' => 2,
                                            ])
                                            ->itemLabel(fn (array $state): ?string => isset($state['category_id']) ? Category::find($state['category_id'])?->category_name : 'New Category'),
                                    ]),

                                Section::make('Units of Measure')
                                    ->collapsible()
                                    ->schema([
                                        Repeater::make('uomAssignments')
                                            ->relationship('uomAssignments')
                                            ->schema([
                                                Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                    'xl' => 4,
                                                ])->schema([
                                                    Select::make('uom_id')
                                                        ->label('UOM')
                                                        ->relationship('uom', 'uom_code')
                                                        ->required()
                                                        ->searchable(),
                                                    Select::make('uom_type')
                                                        ->options(UomType::class)
                                                        ->required()
                                                        ->native(false),
                                                    TextInput::make('conversion_factor')
                                                        ->numeric()
                                                        ->default(1.0)
                                                        ->required(),
                                                    Toggle::make('is_default')
                                                        ->label('Default')
                                                        ->inline(false),
                                                ]),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => isset($state['uom_id']) ? UnitOfMeasure::find($state['uom_id'])?->uom_code : 'New UOM'),
                                    ]),

                                Section::make('Purchasing & Vendor Setup')
                                    ->collapsible()
                                    ->schema([
                                        Repeater::make('vendorItems')
                                            ->relationship('vendorItems')
                                            ->schema([
                                                Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                ])->schema([
                                                    Select::make('vendor_id')
                                                        ->label('Vendor')
                                                        ->relationship('vendor', 'vendor_name')
                                                        ->searchable()
                                                        ->preload()
                                                        ->required(),

                                                    TextInput::make('vendor_item_number')
                                                        ->label('Vendor Part #')
                                                        ->placeholder('Vendor specific SKU')
                                                        ->required(),

                                                    TextInput::make('vendor_item_name')
                                                        ->label('Vendor Item Name')
                                                        ->placeholder('Vendor specific description')
                                                        ->columnSpanFull(),
                                                ]),

                                                Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                    'xl' => 3,
                                                ])->schema([
                                                    TextInput::make('unit_cost')
                                                        ->label('Purchase Price')
                                                        ->numeric()
                                                        ->prefix('$')
                                                        ->required(),

                                                    Select::make('purchase_uom_id')
                                                        ->label('Purchase UOM')
                                                        ->relationship('purchaseUom', 'uom_code')
                                                        ->searchable()
                                                        ->preload()
                                                        ->required()
                                                        ->helperText('The unit used by the vendor for this price.'),

                                                    // In ItemForm.php — Relationships tab, inside the UOM Repeater
                                                    Select::make('uom_type')
                                                        ->options(UomType::class)
                                                        ->required()  // ← Ensure this is present
                                                        ->native(false),

                                                    Select::make('currency_id')
                                                        ->label('Currency')
                                                        ->relationship('currency', 'code')
                                                        ->searchable()
                                                        ->preload()
                                                        ->default(fn () => Currency::where('is_lcy', true)->first()?->id)
                                                        ->required(),
                                                ]),

                                                Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                    'xl' => 3,
                                                ])->schema([
                                                    TextInput::make('lead_time_days')
                                                        ->label('Lead Time (Days)')
                                                        ->numeric()
                                                        ->default(0),

                                                    TextInput::make('minimum_order_qty')
                                                        ->label('MOQ')
                                                        ->numeric()
                                                        ->default(1.0),

                                                    Toggle::make('is_preferred')
                                                        ->label('Preferred Vendor')
                                                        ->inline(false),

                                                    Toggle::make('is_active')
                                                        ->label('Active Pricing')
                                                        ->default(true)
                                                        ->inline(false),
                                                ]),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => isset($state['vendor_id']) ? Vendor::find($state['vendor_id'])?->vendor_name : 'New Vendor Assignment'),
                                    ]),
                            ]),

                        // --- INVENTORY & LOGISTICS TAB ---
                        Tabs\Tab::make('Inventory & Logistics')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'xl' => 3,
                                ])->schema([
                                    TextInput::make('inventory')
                                        ->label('Initial Inventory')
                                        ->required()
                                        ->numeric()
                                        ->disabledOn('edit')
                                        ->helperText('Opening balance (Create only).'),

                                    TextInput::make('reorder_point')
                                        ->numeric()
                                        ->suffix('min'),

                                    TextInput::make('reorder_quantity')
                                        ->numeric()
                                        ->suffix('eoq'),

                                    Select::make('location_id')
                                        ->label('Default Location')
                                        ->relationship('location', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->live(),

                                    Select::make('inventory_bin_id')
                                        ->label('Default Bin')
                                        ->relationship('inventoryBin', 'bin_code', fn ($query, $get) => $query->where('location_id', $get('location_id')))
                                        ->searchable()
                                        ->preload()
                                        ->hidden(fn ($get) => ! $get('location_id')),

                                    TextInput::make('bin_code')
                                        ->label('Default Bin (Legacy)')
                                        ->placeholder('Pick/Put-away default'),

                                    Select::make('item_tracking_code')
                                        ->label('Tracking Code')
                                        ->relationship('itemTrackingCodeDefinition', 'description', fn ($query) => $query->orderBy('code'))
                                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->description}")
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('No tracking'),
                                ]),

                                Fieldset::make('Physical Constraints')
                                    ->schema([
                                        TextInput::make('weight')->numeric()->suffix('kg')->step(0.0001),
                                        TextInput::make('volume')->numeric()->suffix('m³')->step(0.0001),
                                        TextInput::make('shelf_no')->label('Shelf Reference'),
                                    ])->columns([
                                        'default' => 1,
                                        'md' => 3,
                                    ]),
                            ]),

                        // --- POSTING & VAT TAB ---
                        Tabs\Tab::make('Posting & VAT')
                            ->icon('heroicon-o-arrows-right-left')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                ])->schema([
                                    Select::make('general_product_posting_group_id')
                                        ->label('Gen. Prod. Posting Group')
                                        ->relationship('generalProductPostingGroup', 'description')
                                        ->required(),

                                    Select::make('inventory_posting_group_id')
                                        ->label('Inventory Posting Group')
                                        ->relationship('inventoryPostingGroup', 'description')
                                        ->required(),

                                    Select::make('vat_id')
                                        ->label('VAT Code')
                                        ->relationship('vat', 'code')
                                        ->searchable()
                                        ->preload(),

                                    // Added: Missing vat_product_posting_group_id from model
                                    Select::make('vat_product_posting_group_id')
                                        ->label('VAT Prod. Posting Group')
                                        ->relationship('vatProductPostingGroup', 'description')
                                        ->searchable()
                                        ->preload(),

                                    // Removed legacy vat_prod_posting_group text field
                                ]),
                            ]),

                        // --- STATUS TAB ---
                        Tabs\Tab::make('Status')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                ])->schema([
                                    Toggle::make('is_active')
                                        ->label('Active Status')
                                        ->default(true)
                                        ->onColor('success')
                                        ->offColor('danger'),

                                    Toggle::make('blocked')
                                        ->label('Global Block')
                                        ->helperText('Prevents all transactions.'),

                                    Toggle::make('sales_blocked')->label('Sales Blocked'),
                                    Toggle::make('purchasing_blocked')->label('Purchasing Blocked'),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
