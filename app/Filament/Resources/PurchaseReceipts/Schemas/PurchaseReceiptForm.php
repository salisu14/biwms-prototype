<?php

namespace App\Filament\Resources\PurchaseReceipts\Schemas;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Services\Purchase\PurchaseReceiptHeaderPrefillService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
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

                                    Toggle::make('allow_header_override')
                                        ->label('Edit Auto-Filled Header')
                                        ->helperText('Turn on only if you need to override purchase-order-driven header values.')
                                        ->default(false)
                                        ->live()
                                        ->dehydrated(false),

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

                                            $order = PurchaseOrder::with(['vendor.contact', 'location'])->find($state);
                                            if ($order) {
                                                $defaults = app(PurchaseReceiptHeaderPrefillService::class)->defaultsForPurchaseOrder($order);

                                                foreach ($defaults as $field => $value) {
                                                    if ($value !== null) {
                                                        $set($field, $value);
                                                    }
                                                }
                                            }
                                        }),

                                    Placeholder::make('purchase_order_defaults_notice')
                                        ->label('Purchase Order Defaults')
                                        ->content('Header, address, and logistics values are inherited from the selected purchase order. Use the unlock toggles only when you intentionally need to override those defaults.')
                                        ->visible(fn (Get $get): bool => filled($get('purchase_order_id')))
                                        ->columnSpanFull(),

                                    TextInput::make('status')
                                        ->disabled()
                                        ->placeholder('Draft'),

                                    TextInput::make('pay_to_vendor_no')
                                        ->label('Vendor Number')
                                        ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_header_override'))
                                        ->dehydrated(),

                                    TextInput::make('buy_from_vendor_name')
                                        ->label('Vendor Name')
                                        ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_header_override'))
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
                                        ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_header_override'))
                                        ->default(now()),
                                    DatePicker::make('document_date')
                                        ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_header_override'))
                                        ->default(now()),
                                    DatePicker::make('actual_receipt_date'),
                                ]),
                            ]),

                        Tab::make('Addresses')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Buy-from Address')
                                        ->schema([
                                            TextInput::make('buy_from_address')
                                                ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_address_override')),
                                            TextInput::make('buy_from_city')
                                                ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_address_override')),
                                            TextInput::make('buy_from_post_code')
                                                ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_address_override')),
                                            TextInput::make('buy_from_contact')
                                                ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_address_override')),
                                        ])->columnSpan(1),
                                    Section::make('Ship-to Address')
                                        ->schema([
                                            Toggle::make('allow_address_override')
                                                ->label('Edit Auto-Filled Addresses')
                                                ->helperText('Unlock buy-from and ship-to fields when the receipt comes from a purchase order.')
                                                ->default(false)
                                                ->live()
                                                ->dehydrated(false),
                                            TextInput::make('ship_to_name')
                                                ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_address_override')),
                                            TextInput::make('ship_to_address')
                                                ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_address_override')),
                                            TextInput::make('ship_to_city')
                                                ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_address_override')),
                                            TextInput::make('ship_to_contact')
                                                ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_address_override')),
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
                                    TextInput::make('location_code')
                                        ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_logistics_override')),
                                    Select::make('receiving_location_id')
                                        ->relationship('receivingLocation', 'name')
                                        ->disabled(fn (Get $get): bool => filled($get('purchase_order_id')) && ! $get('allow_logistics_override')),
                                    Toggle::make('allow_logistics_override')
                                        ->label('Edit Auto-Filled Logistics')
                                        ->helperText('Unlock receiving location and logistics fields when needed.')
                                        ->default(false)
                                        ->live()
                                        ->dehydrated(false),
                                    TextInput::make('package_tracking_no'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
