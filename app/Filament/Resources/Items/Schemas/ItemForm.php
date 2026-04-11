<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use App\Enums\PriceCalculationMethod;
use App\Enums\UomType;
use App\Models\Category;
use App\Models\UnitOfMeasure;
use App\Models\Vendor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Item Management')
                    ->tabs([
                        // --- GENERAL TAB ---
                        Tab::make('General')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('item_code')
                                        ->label('Item Code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(20)
                                        ->placeholder('e.g., ITEM-001')
                                        ->prefixIcon('heroicon-o-qr-code'),

                                    Select::make('item_type')
                                        ->options(ItemType::options())
                                        ->required()
                                        ->default(ItemType::INVENTORY->value)
                                        ->native(false)
                                        ->live(),

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

                                Grid::make(2)->schema([
                                    Select::make('inventory_method')
                                        ->options(InventoryMethod::options())
                                        ->required()
                                        ->default(InventoryMethod::FIFO->value)
                                        ->native(false)
                                        ->visible(fn ($get) => $get('item_type') !== ItemType::SERVICE->value),

                                    TextInput::make('shelf_life_days')
                                        ->label('Shelf Life (Days)')
                                        ->numeric()
                                        ->integer()
                                        ->placeholder('Leave blank if non-perishable'),
                                ]),
                            ]),

                        // --- PRICING & COSTING TAB ---
                        Tab::make('Pricing & Costing')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Grid::make(3)->schema([
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
                                        ->numeric()
                                        ->prefix('$')
                                        ->step(0.0001),

                                    TextInput::make('profit_percent')
                                        ->label('Profit Margin %')
                                        ->numeric()
                                        ->suffix('%')
                                        ->step(0.01),

                                    Select::make('price_calculation_method')
                                        ->options(PriceCalculationMethod::class)
                                        ->default(PriceCalculationMethod::STANDARD->value)
                                        ->native(false),

                                    TextInput::make('default_price_list_code')
                                        ->label('Price List Ref')
                                        ->placeholder('Internal price list mapping'),
                                ]),

                                Toggle::make('allow_negative_price')
                                    ->label('Allow Negative Pricing')
                                    ->helperText('Used for promotional or discount items.'),
                            ]),

                        // --- RELATIONSHIPS TAB (UOM, CATEGORY, VENDOR) ---
                        Tab::make('Relationships')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Grid::make(1)->schema([
                                    // Categories
                                    Repeater::make('categoryAssignments')
                                        ->relationship('categoryAssignments')
                                        ->schema([
                                            Select::make('category_id')
                                                ->label('Category')
                                                ->relationship('category', 'category_name')
                                                ->searchable()
                                                ->preload()
                                                ->required(),
                                            Toggle::make('is_primary')->label('Primary'),
                                        ])
                                        ->columns(2)
                                        ->itemLabel(fn (array $state): ?string => isset($state['category_id']) ? Category::find($state['category_id'])?->category_name : 'New Category'
                                        )
                                        ->collapsible()
                                        ->collapsed(),

                                    // Units of Measure
                                    Repeater::make('uomAssignments')
                                        ->relationship('uomAssignments')
                                        ->schema([
                                            Select::make('uom_id')
                                                ->label('UOM')
                                                ->relationship('uom', 'uom_code')
                                                ->required(),
                                            Select::make('uom_type')->options(UomType::options())->required(),
                                            TextInput::make('conversion_factor')->numeric()->default(1.0)->required(),
                                            Toggle::make('is_default')->label('Default'),
                                        ])
                                        ->columns(4)
                                        ->itemLabel(fn (array $state): ?string => isset($state['uom_id']) ? UnitOfMeasure::find($state['uom_id'])?->uom_code : 'New UOM'
                                        )
                                        ->collapsible()
                                        ->collapsed(),

                                    // Vendors
                                    Repeater::make('vendorItems')
                                        ->relationship('vendorItems')
                                        ->schema([
                                            Select::make('vendor_id')
                                                ->relationship('vendor', 'vendor_name')
                                                ->required(),
                                            TextInput::make('vendor_item_code')->label('Vendor Part #')->required(),
                                            TextInput::make('unit_cost')->numeric()->prefix('$')->required(),
                                            Toggle::make('is_preferred')->label('Preferred'),
                                        ])
                                        ->columns(4)
                                        ->itemLabel(fn (array $state): ?string => isset($state['vendor_id']) ? Vendor::find($state['vendor_id'])?->vendor_name : 'New Vendor'
                                        )
                                        ->collapsible()
                                        ->collapsed(),
                                ]),
                            ]),

                        // --- INVENTORY & LOGISTICS TAB ---
                        Tab::make('Inventory & Logistics')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('inventory')
                                        ->label('Current Inventory')
                                        ->numeric()
                                        ->disabledOn('edit')
                                        ->helperText('Manual entry only on creation.'),

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
                                        ->preload(),

                                    TextInput::make('bin_code')
                                        ->label('Default Bin')
                                        ->placeholder('Pick/Put-away default'),

                                    TextInput::make('item_tracking_code')
                                        ->label('Tracking Code')
                                        ->placeholder('Serial/Lot rule mapping'),
                                ]),

                                Fieldset::make('Physical Constraints')
                                    ->schema([
                                        TextInput::make('weight')->numeric()->suffix('kg')->step(0.0001),
                                        TextInput::make('volume')->numeric()->suffix('m³')->step(0.0001),
                                        TextInput::make('shelf_no')->label('Shelf Reference'),
                                    ])->columns(3),
                            ]),

                        // --- POSTING & VAT TAB ---
                        Tab::make('Posting & VAT')
                            ->icon('heroicon-o-arrows-right-left')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('general_product_posting_group_id')
                                        ->label('Gen. Prod. Posting Group')
                                        ->relationship('generalProductPostingGroup', 'id')
                                        ->required(),

                                    Select::make('inventory_posting_group_id')
                                        ->label('Inventory Posting Group')
                                        ->relationship('inventoryPostingGroup', 'id')
                                        ->required(),

                                    Select::make('vat_id')
                                        ->label('VAT Code')
                                        ->relationship('vat', 'code')
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('vat_prod_posting_group')
                                        ->label('VAT Prod. Posting Group')
                                        ->placeholder('VAT categorization for posting'),
                                ]),
                            ]),

                        // --- STATUS TAB ---
                        Tab::make('Status')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Grid::make(2)->schema([
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
