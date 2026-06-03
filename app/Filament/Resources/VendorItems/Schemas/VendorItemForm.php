<?php

namespace App\Filament\Resources\VendorItems\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class VendorItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment & Catalog')
                    ->description('Link your internal item to the vendor\'s catalog.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('vendor_id')
                                    ->label('Vendor')
                                    ->relationship('vendor', 'vendor_name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('item_id')
                                    ->label('Item')
                                    ->relationship(
                                        name: 'item',
                                        titleAttribute: 'description',
                                        modifyQueryUsing: fn ($query) => $query->where('blocked', false)->orderBy('item_code')
                                    )
                                    ->searchable()
//                                    ->searchColumns(['item_code', 'description'])
                                    ->getOptionLabelFromRecordUsing(
                                        fn (\App\Models\Item $record) => "{$record->item_code} — {$record->description}"
                                    )
                                    ->preload()
                                    ->required(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('vendor_item_number')
                                    ->label('Vendor SKU / Item No.')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('vendor_item_name')
                                    ->label('Vendor Item Name')
                                    ->maxLength(100)
                                    ->helperText('How the vendor describes this item.'),
                                TextInput::make('vendor_item_category')
                                    ->label('Vendor Category')
                                    ->maxLength(50),
                            ]),
                    ]),

                Section::make('Purchasing Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('purchase_uom_id')
                                    ->label('Purchase UoM')
                                    ->relationship('purchaseUom', 'uom_code')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('The unit of measure the vendor prices in.'),

                                TextInput::make('minimum_order_qty')
                                    ->label('Minimum Order Qty (MOQ)')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->step(0.0001),

                                TextInput::make('lead_time_days')
                                    ->label('Lead Time (Days)')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->integer()
                                    ->minValue(0),
                            ]),
                    ]),

                Section::make('Pricing')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('currency_id')
                                    ->label('Currency')
                                    ->relationship('currency', 'code')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(1) // Adjust to your default currency ID
                                    ->live(),

                                TextInput::make('unit_cost')
                                    ->label('Standard Unit Cost')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.0001)
                                    ->prefix(fn (Get $get) => \App\Models\Currency::find($get('currency_id'))?->symbol ?? '₦'),

                                TextInput::make('last_purchase_price')
                                    ->label('Last Purchase Price')
                                    ->numeric()
                                    ->disabled()
                                    ->step(0.0001)
                                    ->prefix(fn (Get $get) => \App\Models\Currency::find($get('currency_id'))?->symbol ?? '₦')
                                    ->helperText('Auto-updated from purchase orders.'),
                            ]),
                        KeyValue::make('price_breaks')
                            ->label('Quantity Price Breaks')
                            ->keyLabel('Min. Quantity')
                            ->valueLabel('Unit Price')
                            ->keyPlaceholder('e.g., 100')
                            ->valuePlaceholder('e.g., 9.50')
                            ->helperText('Enter whole numbers for quantity. The system will automatically find the best price based on order qty.'),
                    ]),

                Section::make('Validity & Status')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->inline(false)
                                    ->helperText('Can we currently order this from the vendor?'),

                                Toggle::make('is_preferred')
                                    ->label('Preferred Vendor')
                                    ->inline(false)
                                    ->helperText('Setting this as preferred will unset other preferred vendors for this item.'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('effective_date')
                                    ->label('Pricing Effective Date')
                                    ->native(false),
                                DatePicker::make('expiry_date')
                                    ->label('Pricing Expiry Date')
                                    ->native(false)
                                    ->minDate(fn (Get $get) => $get('effective_date')),
                                DatePicker::make('last_purchase_date')
                                    ->label('Last Purchased')
                                    ->disabled(),
                            ]),
                    ]),
            ]);
    }
}
