<?php

namespace App\Filament\Resources\BlanketOrders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BlanketOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('document_number')
                    ->required(),
                TextInput::make('external_document_no'),
                Select::make('vendor_id')
                    ->relationship('vendor', 'id')
                    ->required(),
                TextInput::make('document_type')
                    ->required()
                    ->default('BLANKET_ORDER'),
                TextInput::make('status')
                    ->required()
                    ->default('OPEN'),
                DatePicker::make('posting_date'),
                DatePicker::make('document_date'),
                DatePicker::make('order_date'),
                DatePicker::make('starting_date'),
                DatePicker::make('ending_date'),
                Select::make('buyer_id')
                    ->relationship('buyer', 'name'),
                TextInput::make('responsibility_center'),
                Select::make('assigned_user_id')
                    ->relationship('assignedUser', 'name'),
                TextInput::make('project_code'),
                TextInput::make('department_code'),
                TextInput::make('shortcut_dimension_1_code'),
                TextInput::make('shortcut_dimension_2_code'),
                TextInput::make('dimension_set_id')
                    ->numeric(),
                TextInput::make('vendor_order_no'),
                TextInput::make('purchase_order_no'),
                TextInput::make('order_address_code'),
                TextInput::make('currency_code'),
                TextInput::make('exchange_rate')
                    ->numeric(),
                Toggle::make('prices_including_vat')
                    ->required(),
                TextInput::make('payment_terms_code'),
                TextInput::make('payment_method_code'),
                TextInput::make('transaction_type'),
                TextInput::make('transaction_specification'),
                TextInput::make('transport_method'),
                TextInput::make('entry_point'),
                TextInput::make('area'),
                TextInput::make('language_code'),
                TextInput::make('format_region'),
                TextInput::make('buy_from_vendor_name'),
                TextInput::make('buy_from_address'),
                TextInput::make('buy_from_address_2'),
                TextInput::make('buy_from_city'),
                TextInput::make('buy_from_post_code'),
                TextInput::make('buy_from_county'),
                TextInput::make('buy_from_country_region_code'),
                TextInput::make('buy_from_contact'),
                TextInput::make('pay_to_vendor_no'),
                TextInput::make('pay_to_name'),
                TextInput::make('pay_to_address'),
                TextInput::make('pay_to_address_2'),
                TextInput::make('pay_to_city'),
                TextInput::make('pay_to_post_code'),
                TextInput::make('pay_to_county'),
                TextInput::make('pay_to_country_region_code'),
                TextInput::make('pay_to_contact'),
                TextInput::make('ship_to_code'),
                TextInput::make('ship_to_name'),
                TextInput::make('ship_to_address'),
                TextInput::make('ship_to_address_2'),
                TextInput::make('ship_to_city'),
                TextInput::make('ship_to_post_code'),
                TextInput::make('ship_to_county'),
                TextInput::make('ship_to_country_region_code'),
                TextInput::make('ship_to_contact'),
                TextInput::make('location_code'),
                TextInput::make('shipment_method_code'),
                TextInput::make('shipping_agent_code'),
                TextInput::make('shipping_agent_service_code'),
                TextInput::make('package_tracking_no'),
                TextInput::make('invoice_disc_code'),
                DatePicker::make('requested_receipt_date'),
                DatePicker::make('promised_receipt_date'),
                TextInput::make('quote_no'),
                Textarea::make('comment')
                    ->columnSpanFull(),
                Toggle::make('released')
                    ->required(),
                DateTimePicker::make('released_at'),
                TextInput::make('released_by')
                    ->numeric(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('last_modified_by')
                    ->numeric(),
            ]);
    }
}
