<?php

namespace App\Filament\Resources\PurchaseReceipts\Schemas;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Purchase Receipt')
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('document_number')
                                        ->label('Receipt No.')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(50)
                                        // Lock the field if the record already exists in the database
                                        ->disabled(fn (?PurchaseReceipt $record) => $record !== null)
                                        // Ensure the value is still sent to the database during creation
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The code cannot be changed once the Purchase receipt is created.'),

                                    Select::make('purchase_order_id')
                                        ->label('Purchase Order')
                                        ->relationship('purchaseOrder', 'order_number')
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if (! $state) {
                                                return;
                                            }

                                            $order = PurchaseOrder::with('vendor')->find($state);
                                            if ($order) {
                                                $set('purchase_order_no', $order->order_number);
                                                $set('vendor_id', $order->vendor_id);
                                                $set('pay_to_vendor_no', $order->vendor?->vendor_number ?? '');
                                                $set('buy_from_vendor_name', $order->vendor_name);

                                                // Automatically pull vendor address details if available on order
                                                if ($order->vendor) {
                                                    $set('buy_from_address', $order->vendor->address);
                                                    $set('buy_from_city', $order->vendor->city);
                                                    $set('buy_from_post_code', $order->vendor->post_code);
                                                }
                                            }
                                        }),

                                    TextInput::make('status')
                                        ->disabled()
                                        ->placeholder('Draft'),

                                    TextInput::make('pay_to_vendor_no')
                                        ->label('Vendor Number')
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('buy_from_vendor_name')
                                        ->label('Vendor Name')
                                        ->disabled()
                                        ->dehydrated(),

                                    Select::make('vendor_id')
                                        ->relationship('vendor', 'vendor_name')
                                        ->label('Vendor Link')
                                        ->disabled()
                                        ->dehydrated()
                                        ->hidden(),

                                    TextInput::make('external_document_no')
                                        ->label('Vendor Shipment No.'),

                                    DatePicker::make('posting_date')
                                        ->default(now()),
                                    DatePicker::make('document_date')
                                        ->default(now()),
                                    DatePicker::make('actual_receipt_date'),
                                ]),
                            ]),

                        Tab::make('Addresses')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Buy-from Address')
                                        ->schema([
                                            TextInput::make('buy_from_address'),
                                            TextInput::make('buy_from_city'),
                                            TextInput::make('buy_from_post_code'),
                                            TextInput::make('buy_from_contact'),
                                        ])->columnSpan(1),
                                    Section::make('Ship-to Address')
                                        ->schema([
                                            TextInput::make('ship_to_name'),
                                            TextInput::make('ship_to_address'),
                                            TextInput::make('ship_to_city'),
                                            TextInput::make('ship_to_contact'),
                                        ])->columnSpan(1),
                                ]),
                            ]),

                        Tab::make('Audit & Posting')
                            ->schema([
                                Grid::make(3)->schema([
                                    Toggle::make('posted')
                                        ->disabled(),
                                    DateTimePicker::make('posted_at')
                                        ->disabled(),
                                    Select::make('posted_by')
                                        ->relationship('postedByUser', 'name')
                                        ->disabled(),
                                    TextInput::make('location_code'),
                                    Select::make('receiving_location_id')
                                        ->relationship('receivingLocation', 'name'),
                                    TextInput::make('package_tracking_no'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
