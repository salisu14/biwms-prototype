<?php

namespace App\Filament\Resources\BlanketSalesOrders\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class BlanketSalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Blanket Sales Order')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-document-duplicate')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('document_number')
                                        ->label('BO Number')
                                        ->required()
                                        ->unique(ignoreRecord: true),
                                    TextInput::make('status')
                                        ->default('OPEN')
                                        ->disabled()
                                        ->dehydrated(),
                                    TextInput::make('external_document_no')
                                        ->label('External Doc No.'),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('customer_id')
                                        ->relationship('customer', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($state, Set $set) => self::updateCustomerDetails($state, $set)),
                                    Select::make('salesperson_code')
                                        ->label('Salesperson')
                                        ->relationship('salesperson', 'name')
                                        ->searchable()
                                        ->preload(),
                                    Select::make('assigned_user_id')
                                        ->label('Assigned User')
                                        ->relationship('assignedUser', 'name')
                                        ->searchable(),
                                    TextInput::make('currency_code')
                                        ->default('USD'),
                                ]),
                            ]),

                        Tab::make('Customer & Addresses')
                            ->icon('heroicon-m-map-pin')
                            ->schema([
                                Section::make('Sell-to Customer')
                                    ->schema([
                                        TextInput::make('sell_to_customer_name')->label('Name'),
                                        TextInput::make('sell_to_address')->label('Address'),
                                        Grid::make(3)->schema([
                                            TextInput::make('sell_to_city')->label('City'),
                                            TextInput::make('sell_to_post_code')->label('Post Code'),
                                            TextInput::make('sell_to_contact')->label('Contact'),
                                        ]),
                                    ]),
                                Section::make('Bill-to Customer')
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('bill_to_name'),
                                        TextInput::make('bill_to_address'),
                                        Grid::make(2)->schema([
                                            TextInput::make('bill_to_city'),
                                            TextInput::make('bill_to_post_code'),
                                        ]),
                                    ]),
                                Section::make('Shipping Details')
                                    ->collapsed()
                                    ->schema([
                                        TextInput::make('ship_to_name'),
                                        TextInput::make('ship_to_address'),
                                        Grid::make(3)->schema([
                                            TextInput::make('ship_to_city'),
                                            TextInput::make('location_code'),
                                            TextInput::make('shipment_method_code'),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Dates & Logistics')
                            ->icon('heroicon-m-calendar')
                            ->schema([
                                Grid::make(3)->schema([
                                    DatePicker::make('order_date')->default(now()),
                                    DatePicker::make('posting_date'),
                                    DatePicker::make('document_date'),
                                    DatePicker::make('starting_date')->label('Valid From'),
                                    DatePicker::make('ending_date')->label('Valid To'),
                                    DatePicker::make('requested_receipt_date'),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('payment_terms_code'),
                                    TextInput::make('payment_method_code'),
                                    TextInput::make('shipping_agent_code'),
                                ]),
                            ]),

                        Tab::make('Audit & Status')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('released')
                                        ->disabled()
                                        ->onColor('success'),
                                    DateTimePicker::make('released_at')
                                        ->disabled(),
                                    Select::make('released_by')
                                        ->relationship('releasedByUser', 'name')
                                        ->disabled(),
                                ]),
                                Textarea::make('comment')->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    protected static function updateCustomerDetails($state, Set $set): void
    {
        if (! $state) {
            return;
        }

        $customer = Customer::find($state);
        if ($customer) {
            $set('sell_to_customer_name', $customer->name);
            $set('sell_to_address', $customer->address);
            $set('sell_to_city', $customer->city);
            $set('sell_to_post_code', $customer->post_code);
            $set('sell_to_customer_no', $customer->customer_no);

            $set('bill_to_name', $customer->name);
            $set('bill_to_address', $customer->address);
            $set('bill_to_city', $customer->city);
            $set('bill_to_post_code', $customer->post_code);

            $set('currency_code', $customer->currency_code);
        }
    }
}
