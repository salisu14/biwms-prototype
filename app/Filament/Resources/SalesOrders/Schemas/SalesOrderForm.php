<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\ShippingMethod;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Services\Sales\SalesPricingResolver;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Order Tabs')
                            ->tabs([
                                Tab::make('General & Items')
                                    ->schema([
                                        Section::make('General Information')
                                            ->schema([
                                                TextInput::make('order_number')
                                                    ->label('Order Number')
                                                    ->default(fn () => 'Draft')
                                                    ->disabled()
                                                    // Lock the field if the record already exists in the database
                                                    ->disabled(fn (?SalesOrder $record) => $record !== null)
                                                    // Ensure the value is still sent to the database during creation
                                                    ->dehydrated(false)
                                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                                    ->helperText('The code cannot be changed once the Sales order is created.'),

                                                Select::make('order_type')
                                                    ->options(SalesOrderType::class)
                                                    ->default(SalesOrderType::SalesOrder)
                                                    ->required()
                                                    ->native(false),

                                                Select::make('customer_id')
                                                    ->relationship('customer', 'name')
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Set $set) {
                                                        if ($state) {
                                                            $customer = Customer::find($state);
                                                            $set('customer_name', $customer->name);
                                                            $set('customer_address', $customer->address);
                                                            $set('ship_to_name', $customer->name);
                                                            $set('ship_to_address', $customer->address);
                                                            $set('currency_code', $customer->currency_code ?? 'NGN');
                                                            $set('payment_terms_code', $customer->payment_terms_code);
                                                            $set('general_business_posting_group_id', $customer->general_business_posting_group_id);
                                                        }
                                                    }),

                                                TextInput::make('external_document_number')
                                                    ->label('PO / External Ref'),
                                            ])->columns(2),

                                        Section::make('Order Lines')
                                            ->schema([
                                                Repeater::make('lines')
                                                    ->relationship()
                                                    ->live()
                                                    ->schema([
                                                        Grid::make(12)
                                                            ->schema([
                                                                Select::make('item_id')
                                                                    ->label('Item No.')
                                                                    ->relationship('item', 'item_code', fn ($query) => $query->finishedGoods()->where('blocked', false))
                                                                    ->searchable()
                                                                    ->preload()
                                                                    ->required()
                                                                    ->live()
                                                                    ->columnSpan(3)
                                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                                        if ($state) {
                                                                            $item = Item::find($state);
                                                                            $defaultSalesUom = $item?->uoms()
                                                                                ->wherePivot('uom_type', 'SALES')
                                                                                ->wherePivot('is_default', true)
                                                                                ->first();
                                                                            $defaultUomCode = $defaultSalesUom?->uom_code ?? $item?->base_unit_of_measure;
                                                                            $customer = Customer::find((int) $get('../../customer_id'));
                                                                            $location = Location::find((int) $get('../../location_id'));
                                                                            $quantity = (float) ($get('quantity') ?? 1);
                                                                            $pricing = app(SalesPricingResolver::class)->resolve(
                                                                                item: $item,
                                                                                customer: $customer,
                                                                                quantity: $quantity,
                                                                                variantCode: null,
                                                                                uom: $defaultUomCode,
                                                                                location: $location
                                                                            );
                                                                            $conversionFactor = $item?->getConversionFactorForUom($defaultUomCode) ?? 1;

                                                                            $set('description', $item?->description);
                                                                            $set('item_code', $item?->item_code);
                                                                            $set('unit_price', $pricing['unit_price']);
                                                                            $set('line_discount_percent', $pricing['discount_percent']);
                                                                            $set('price_source', $pricing['price_source']);
                                                                            $set('pricing_master_id', $pricing['pricing_master_id']);
                                                                            $set('unit_of_measure_code', $defaultUomCode);
                                                                            $set('qty_per_unit_of_measure', $conversionFactor);
                                                                        }
                                                                    }),

                                                                TextInput::make('description')
                                                                    ->label('Description')
                                                                    ->columnSpan(5)
                                                                    ->placeholder('Select an item to see description')
                                                                    ->readOnly(),

                                                                TextInput::make('quantity')
                                                                    ->numeric()
                                                                    ->default(1)
                                                                    ->required()
                                                                    ->columnSpan(2)
                                                                    ->live(onBlur: true),

                                                                TextInput::make('unit_price')
                                                                    ->label('Price')
                                                                    ->numeric()
                                                                    ->required()
                                                                    ->columnSpan(2)
                                                                    ->live(onBlur: true)
                                                                    ->prefix(fn ($get) => $get('../../currency_code') ?? 'NGN'),

                                                                TextInput::make('line_discount_percent')
                                                                    ->label('Disc. %')
                                                                    ->numeric()
                                                                    ->default(0)
                                                                    ->suffix('%')
                                                                    ->columnSpan(2)
                                                                    ->live(onBlur: true),

                                                                // Hidden/Technical Row
                                                                TextInput::make('item_code')
                                                                    ->label('Code')
                                                                    ->disabled()
                                                                    ->dehydrated()
                                                                    ->columnSpan(2),

                                                                TextInput::make('price_source')
                                                                    ->hidden()
                                                                    ->dehydrated(),

                                                                TextInput::make('pricing_master_id')
                                                                    ->hidden()
                                                                    ->dehydrated(),

                                                                Select::make('unit_of_measure_code')
                                                                    ->label('UOM')
                                                                    ->options(function ($get) {
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
                                                                                $uom->uom_code => $uom->uom_code,
                                                                            ])
                                                                            ->toArray();

                                                                        if (! array_key_exists($item->base_unit_of_measure, $uoms)) {
                                                                            $uoms[$item->base_unit_of_measure] = $item->base_unit_of_measure;
                                                                        }

                                                                        return $uoms;
                                                                    })
                                                                    ->required()
                                                                    ->live()
                                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                                        $itemId = $get('item_id');
                                                                        if (! $itemId) {
                                                                            return;
                                                                        }

                                                                        $item = Item::find($itemId);
                                                                        $customer = Customer::find((int) $get('../../customer_id'));
                                                                        $location = Location::find((int) $get('../../location_id'));
                                                                        $currentQuantity = (float) ($get('quantity') ?? 1);
                                                                        $conversionFactor = $item?->getConversionFactorForUom($state) ?? 1;
                                                                        $newPricing = app(SalesPricingResolver::class)->resolve(
                                                                            item: $item,
                                                                            customer: $customer,
                                                                            quantity: $currentQuantity,
                                                                            variantCode: null,
                                                                            uom: $state,
                                                                            location: $location
                                                                        );

                                                                        $set('qty_per_unit_of_measure', $conversionFactor);
                                                                        $set('unit_price', $newPricing['unit_price']);
                                                                        $set('line_discount_percent', $newPricing['discount_percent']);
                                                                        $set('price_source', $newPricing['price_source']);
                                                                        $set('pricing_master_id', $newPricing['pricing_master_id']);
                                                                    })
                                                                    ->columnSpan(2),

                                                                TextInput::make('qty_per_unit_of_measure')
                                                                    ->label('Qty/UOM')
                                                                    ->numeric()
                                                                    ->readOnly()
                                                                    ->dehydrated()
                                                                    ->columnSpan(2),
                                                            ]),
                                                    ])
                                                    ->reorderableWithButtons()
                                                    ->addActionLabel('Add Item Line')
                                                    ->itemLabel(fn (array $state): ?string => $state['item_code'] ?? null),
                                            ]),
                                    ]),

                                Tab::make('Logistics & Shipping')
                                    ->schema([
                                        Section::make('Shipping Details')
                                            ->schema([
                                                Select::make('location_id')
                                                    ->relationship('location', 'name')
                                                    ->searchable(),
                                                Select::make('shipping_method')
                                                    ->options(ShippingMethod::class)
                                                    ->native(false),
                                                TextInput::make('shipping_agent_code'),
                                                TextInput::make('shipping_agent_service_code'),
                                                DatePicker::make('requested_delivery_date'),
                                                DatePicker::make('promised_delivery_date'),
                                            ])->columns(2),

                                        Section::make('Addresses')
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        Group::make([
                                                            TextInput::make('customer_name')->required(),
                                                            Textarea::make('customer_address')->rows(2),
                                                        ]),
                                                        Group::make([
                                                            TextInput::make('ship_to_name'),
                                                            Textarea::make('ship_to_address')->rows(2),
                                                        ]),
                                                    ]),
                                            ]),
                                    ]),

                                Tab::make('Notes')
                                    ->schema([
                                        Section::make('Additional Information')
                                            ->schema([
                                                Textarea::make('customer_comment')
                                                    ->label('Customer Notes (Visible on Invoice)')
                                                    ->rows(4),
                                                Textarea::make('internal_comment')
                                                    ->label('Internal Notes (Hidden from Customer)')
                                                    ->rows(4),
                                            ])->columns(1),
                                    ]),
                            ])->persistTabInQueryString(),
                    ])->columnSpan(['lg' => 2])
                    ->disabled(fn ($record) => $record &&
                        $record->status === SalesOrderStatus::APPROVED &&
                        ! auth()->user()?->hasRole('SUPER_ADMIN')
                    ),

                Group::make()
                    ->schema([
                        Section::make('Status & Dates')
                            ->schema([
                                Select::make('status')
                                    ->options(SalesOrderStatus::class)
                                    ->default(SalesOrderStatus::DRAFT)
                                    ->required()
                                    ->native(false),
                                DatePicker::make('order_date')
                                    ->default(now())
                                    ->required(),
                                DatePicker::make('posting_date'),
                                Select::make('salesperson_id')
                                    ->relationship('salesperson', 'name')
                                    ->searchable(),
                            ]),

                        Section::make('Financial Totals')
                            ->schema([
                                TextInput::make('currency_code')
                                    ->default('NGN')
                                    ->required(),
                                TextInput::make('grand_total')
                                    ->label('Order Grand Total')
                                    ->numeric()
                                    ->prefix(fn ($get) => $get('currency_code'))
                                    ->readOnly()
                                    ->placeholder(function ($get) {
                                        $lines = $get('lines') ?? [];
                                        $total = collect($lines)->sum(function ($line) {
                                            $qty = (float) ($line['quantity'] ?? 0);
                                            $price = (float) ($line['unit_price'] ?? 0);

                                            return $qty * $price;
                                        });

                                        return number_format($total, 2);
                                    })
                                    ->extraInputAttributes(['class' => 'font-bold text-lg']),

                                Grid::make(2)
                                    ->schema([
                                        Toggle::make('fully_shipped')->disabled(),
                                        Toggle::make('fully_invoiced')->disabled(),
                                    ]),
                            ]),
                    ])->columnSpan(['lg' => 1])
                    ->disabled(fn ($record) => $record &&
                        $record->status === SalesOrderStatus::APPROVED &&
                        ! auth()->user()?->hasRole('SUPER_ADMIN')
                    ),
            ])->columns(3);
    }
}
