<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\ShippingMethod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')
                    ->required(),
                TextInput::make('external_document_number'),
                Select::make('order_type')
                    ->options(SalesOrderType::class)
                    ->default('SALES_ORDER')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                TextInput::make('customer_name')
                    ->required(),
                TextInput::make('customer_address'),
                TextInput::make('ship_to_name'),
                TextInput::make('ship_to_address'),
                Select::make('general_business_posting_group_id')
                    ->relationship('generalBusinessPostingGroup', 'id'),
                Select::make('customer_posting_group_id')
                    ->relationship('customerPostingGroup', 'id'),
                TextInput::make('vat_bus_posting_group'),
                Select::make('pricing_group_id')
                    ->relationship('pricingGroup', 'name'),
                Select::make('location_id')
                    ->relationship('location', 'name'),
                TextInput::make('shipping_agent_code'),
                TextInput::make('shipping_agent_service_code'),
                Select::make('shipping_method')
                    ->options(ShippingMethod::class),
                DatePicker::make('order_date')
                    ->required(),
                DatePicker::make('posting_date'),
                DatePicker::make('requested_delivery_date'),
                DatePicker::make('promised_delivery_date'),
                DatePicker::make('shipment_date'),
                TextInput::make('payment_terms_code'),
                TextInput::make('payment_method_code'),
                TextInput::make('subtotal')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('line_discount_total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('invoice_discount_percent')
                    ->numeric(),
                TextInput::make('invoice_discount_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_vat')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('grand_total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('currency_code')
                    ->required()
                    ->default('USD'),
                TextInput::make('currency_factor')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('status')
                    ->options(SalesOrderStatus::class)
                    ->default('DRAFT')
                    ->required(),
                TextInput::make('quantity_shipped')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('quantity_invoiced')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('fully_shipped')
                    ->required(),
                Toggle::make('fully_invoiced')
                    ->required(),
                Select::make('salesperson_id')
                    ->relationship('salesperson', 'name'),
                TextInput::make('assigned_warehouse_worker_id')
                    ->numeric(),
                TextInput::make('approved_by')
                    ->numeric(),
                DateTimePicker::make('approved_at'),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('cancelled_at'),
                TextInput::make('cancelled_by')
                    ->numeric(),
                TextInput::make('cancellation_reason'),
                TextInput::make('dimensions'),
                Textarea::make('internal_comment')
                    ->columnSpanFull(),
                Textarea::make('customer_comment')
                    ->columnSpanFull(),
            ]);
    }
}
