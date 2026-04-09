<?php

namespace App\Filament\Resources\SalesShipmentHeaders\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class SalesShipmentHeaderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Shipment Details')
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('document_no')
                                        ->label('Document No.')
                                        ->required(),
                                    //                                        ->disabled(),
                                    // ... inside the General tab schema grid ...
                                    Select::make('sell_to_customer_no')
                                        ->label('Sell-to Customer No.')
                                        ->relationship('customer', 'customer_number')
                                        ->searchable()
                                        ->preload()
                                        ->live() // Essential to trigger reactivity
                                        ->required()
                                        ->afterStateUpdated(function (?string $state, $set) {
                                            if (blank($state)) {
                                                $set('sell_to_customer_name', null);

                                                return;
                                            }

                                            // Fetch the customer and populate the name
                                            $customer = Customer::where('customer_number', $state)->first();

                                            if ($customer) {
                                                $set('sell_to_customer_name', $customer->name);
                                            }
                                        }),

                                    TextInput::make('sell_to_customer_name')
                                        ->label('Sell-to Customer Name')
                                        ->required()
                                        ->maxLength(100),
                                ]),
                                Grid::make(3)->schema([
                                    DatePicker::make('posting_date')
                                        ->label('Posting Date')
                                        ->required(),
                                    DatePicker::make('document_date')
                                        ->label('Document Date')
                                        ->required(),
                                    DatePicker::make('shipment_date')
                                        ->label('Shipment Date')
                                        ->required(),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('order_no')
                                        ->label('Order No.')
                                        ->disabled(),
                                    TextInput::make('external_document_no')
                                        ->label('External Document No.'),
                                    TextInput::make('salesperson_code')
                                        ->label('Salesperson Code'),
                                ]),
                            ]),

                        Tab::make('Shipping & Billing')
                            ->schema([
                                Section::make('Ship-to Address')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('ship_to_code'),
                                        TextInput::make('ship_to_name'),
                                        TextInput::make('ship_to_address'),
                                        TextInput::make('ship_to_city'),
                                        TextInput::make('ship_to_post_code'),
                                        TextInput::make('ship_to_country_region_code'),
                                    ]),
                                Section::make('Billing Details')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('bill_to_customer_no'),
                                        TextInput::make('bill_to_name'),
                                        TextInput::make('currency_code'),
                                        Toggle::make('prices_including_vat'),
                                    ]),
                            ]),

                        Tab::make('Foreign Trade & Dimensions')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('shortcut_dimension_1_code')
                                        ->label('Global Dimension 1'),
                                    TextInput::make('shortcut_dimension_2_code')
                                        ->label('Global Dimension 2'),
                                    Select::make('dimension_set_id')
                                        ->relationship('dimensionSet', 'id')
                                        ->label('Dimension Set ID'),
                                    TextInput::make('location_code'),
                                    TextInput::make('shipping_agent_code'),
                                    TextInput::make('package_tracking_no'),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
