<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\ShippingMethod;
use App\Models\Customer;
use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
                        Section::make('General Information')
                            ->schema([
                                TextInput::make('order_number')
                                    ->default(fn () => 'Draft')
                                    ->disabled()
                                    ->dehydrated(false),

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
                            ->headerActions([
                                // Optional: You could add custom actions here
                            ])
                            ->schema([
                                Repeater::make('lines')
                                    ->relationship()
                                    ->live()
                                    ->schema([
                                        Select::make('item_id')
                                            ->relationship('item', 'description', fn ($query) => $query->where('item_type', 'FINISHED_GOOD'))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            // Fetches the unit price from the Item model when selected
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if ($state) {
                                                    $item = Item::find($state);
                                                    $set('unit_price', $item?->price ?? 0);
                                                }
                                            }),

                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required(),

                                        TextInput::make('unit_price')
                                            ->numeric()
                                            ->required()
                                            ->prefix(fn ($get) => $get('../../currency_code') ?? 'NGN'),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(1)
                                    ->reorderableWithButtons()
                                    ->addActionLabel('Add Item Line'),
                            ]),

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
                            ])->collapsed(),

                        Section::make('Logistics & Shipping')
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
                            ])->columns(2)->collapsed(),
                    ])->columnSpan(['lg' => 2]),

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
                                    ->extraInputAttributes(['class' => 'font-bold text-lg']),

                                Grid::make(2)
                                    ->schema([
                                        Toggle::make('fully_shipped')->disabled(),
                                        Toggle::make('fully_invoiced')->disabled(),
                                    ]),
                            ]),
                    ])->columnSpan(['lg' => 1]),

                Section::make('Additional Details')
                    ->schema([
                        Textarea::make('customer_comment')
                            ->label('Customer Notes (Visible on Invoice)')
                            ->columnSpan(1),
                        Textarea::make('internal_comment')
                            ->label('Internal Notes (Hidden from Customer)')
                            ->columnSpan(1),
                    ])->columns(2)->collapsed(),
            ])->columns(3);
    }
}
