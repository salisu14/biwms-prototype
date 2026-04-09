<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                Tabs::make('Item Details')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('item_number')
                                        ->label('Item Number/SKU')
                                        ->required()
                                        ->unique(ignoreRecord: true),
                                    Select::make('item_type')
                                        ->options(ItemType::options())
                                        ->required()
                                        ->native(false),
                                    TextInput::make('description')
                                        ->required()
                                        ->columnSpanFull(),
                                    Textarea::make('description_2')
                                        ->label('Extended Description')
                                        ->columnSpanFull(),
                                ]),
                            ]),

                        Tab::make('Pricing & Costing')
                            ->icon('heroicon-m-currency-dollar')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('unit_price')
                                        ->label('Sales Price')
                                        ->numeric()
                                        ->prefix('$')
                                        ->required(),
                                    TextInput::make('unit_cost')
                                        ->label('Unit Cost')
                                        ->numeric()
                                        ->prefix('$')
                                        ->required(),
                                    TextInput::make('standard_cost')
                                        ->numeric()
                                        ->prefix('$'),

                                    Select::make('inventory_method')
                                        ->label('Inventory Method')
                                        ->options(\App\Enums\InventoryMethod::class)
                                        ->required()
                                        ->native(false),
                                    TextInput::make('costing_method')
                                        ->default('AVERAGE')
                                        ->hidden(), // Deprecated in favor of inventory_method
                                    TextInput::make('profit_percent')
                                        ->label('Profit %')
                                        ->numeric()
                                        ->suffix('%'),
                                ]),
                            ]),

                        Tab::make('Inventory & Logistics')
                            ->icon('heroicon-m-cube')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('uom_id')
                                        ->label('Base Unit of Measure')
                                        ->relationship('uom', 'uom_code')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    
                                    Select::make('sku_id')
                                        ->label('Default SKU/Variant')
                                        ->relationship('sku', 'sku_code')
                                        ->searchable()
                                        ->placeholder('Auto-generated if blank'),

                                    Select::make('location_id')
                                        ->label('Default Location')
                                        ->relationship('location', 'name')
                                        ->searchable(),

                                    TextInput::make('inventory')
                                        ->label('Initial Inventory')
                                        ->numeric()
                                        ->default(0)
                                        ->disabledOn('edit'),
                                    
                                    TextInput::make('reorder_point')
                                        ->numeric(),
                                    TextInput::make('reorder_quantity')
                                        ->numeric(),
                                    TextInput::make('bin_code')
                                        ->label('Default Bin'),
                                ]),
                                Fieldset::make('Physical Attributes')
                                    ->schema([
                                        TextInput::make('weight')->numeric()->suffix('kg'),
                                        TextInput::make('volume')->numeric()->suffix('m³'),
                                        TextInput::make('shelf_no'),
                                    ])->columns(3),
                            ]),

                        Tab::make('Posting & VAT')
                            ->icon('heroicon-m-arrows-right-left')
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
                                        ->label('VAT Configuration')
                                        ->relationship('vat', 'code')
                                        ->searchable()
                                        ->preload(),

                                    Select::make('general_posting_setup_id')
                                        ->label('Default Gen. Posting Setup')
                                        ->relationship('generalPostingSetup', 'id')
                                        ->placeholder('System Resolved'),
                                    
                                    Select::make('inventory_posting_setup_id')
                                        ->label('Default Inv. Posting Setup')
                                        ->relationship('inventoryPostingSetup', 'id')
                                        ->placeholder('Location Resolved'),
                                ]),
                            ]),

                        Tab::make('Settings')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('blocked')
                                        ->label('Blocked (Global)')
                                        ->helperText('Prevents all transactions for this item.'),
                                    Toggle::make('sales_blocked')
                                        ->label('Blocked for Sales'),
                                    Toggle::make('purchasing_blocked')
                                        ->label('Blocked for Purchasing'),
                                    Toggle::make('is_active')
                                        ->default(true)
                                        ->label('Active Status'),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
