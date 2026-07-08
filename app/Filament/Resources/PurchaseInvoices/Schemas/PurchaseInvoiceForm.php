<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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
use Illuminate\Database\Eloquent\Builder;

class PurchaseInvoiceForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Purchase Invoice')
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'xl' => 3,
                                ])->schema([
                                    static::makeSystemGeneratedTextInput(
                                        'document_number',
                                        'Invoice Number',
                                        'Generated automatically from the purchase invoice number series and cannot be changed.'
                                    ),

                                    Select::make('vendor_id')
                                        ->relationship('vendor', 'vendor_name')
                                        ->label('Vendor')
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $set('order_id', null);

                                            if (! $state) {
                                                $set('vendor_name', null);
                                                $set('vendor_address', null);

                                                return;
                                            }

                                            $vendor = Vendor::find($state);
                                            if ($vendor) {
                                                $set('vendor_name', $vendor->vendor_name);
                                                $set('vendor_address', $vendor->address);
                                            }
                                        }),

                                    Select::make('order_id')
                                        ->label('Purchase Order')
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                       // Logic: Filter orders by the selected Vendor AND status is OPEN
                                        ->relationship(
                                            name: 'purchaseOrder',
                                            titleAttribute: 'order_number',
                                            modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                                ->when(
                                                    $get('vendor_id'),
                                                    fn ($q) => $q->where('vendor_id', $get('vendor_id'))
                                                )
                                                ->whereIn('status', [
                                                    PurchaseOrderStatus::APPROVED->value,
                                                    PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
                                                    PurchaseOrderStatus::RECEIVED->value,
                                                ])
                                                ->orderBy('order_date', 'desc')
                                        )
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if (! $state) {
                                                return;
                                            }

                                            // Eager load vendor to avoid N+1 queries during this operation
                                            $order = PurchaseOrder::with('vendor')->find($state);

                                            if ($order) {
                                                // 1. Link the Vendor
                                                $set('vendor_id', $order->vendor_id);
                                                $set('order_number', $order->order_number);

                                                // 2. Buy-From Vendor Logic
                                                // BC Logic: Prefer the snapshot on the Order, fallback to Vendor Master Data
                                                $set('vendor_name', $order->vendor_name ?? $order->vendor->vendor_name ?? null);

                                                // 3. Pay-To Vendor Logic
                                                // BC Logic: Usually Buy-From and Pay-To are the same unless specified.
                                                // Map Vendor 'vendor_code' to 'pay_to_vendor_no'
                                                $set('pay_to_vendor_no', $order->vendor->vendor_code ?? null);
                                                $set('pay_to_name', $order->vendor->vendor_name ?? null); // Assuming you have this field or need it

                                                // 4. Addresses
                                                // Pull from Vendor Master Data (Order usually just references the Vendor ID)
                                                if ($order->vendor) {
                                                    $set('vendor_address', $order->vendor->address);
                                                    $set('buy_from_city', $order->vendor->city);
                                                    $set('buy_from_post_code', $order->vendor->postal_code); // Vendor model uses 'postal_code'
                                                    $set('buy_from_contact', $order->vendor->contact_person);

                                                    // Also fill Pay-to address if it differs, otherwise same as Buy-from
                                                    $set('pay_to_address', $order->vendor->address);
                                                    $set('pay_to_city', $order->vendor->city);
                                                    $set('pay_to_post_code', $order->vendor->postal_code);
                                                }
                                            }
                                        }),

                                    Select::make('status')
                                        ->options(ApprovalStatus::class)
                                        ->default(ApprovalStatus::DRAFT)
                                        ->required()
                                        ->native(false),

                                    // Pay-To Vendor Details (Standard BC Fields)
                                    TextInput::make('pay_to_vendor_no')
                                        ->label('Pay-to Vendor No.')
                                        ->dehydrated(),

                                    TextInput::make('vendor_name')
                                        ->label('Buy-from Vendor Name')
                                        ->disabled()
                                        ->dehydrated()
                                        ->required(),

                                    TextInput::make('pay_to_name')
                                        ->label('Pay-to Name')
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('external_document_number')
                                        ->label('Vendor Invoice No.')
                                        ->hint('The invoice number from the vendor'),

                                    DatePicker::make('posting_date')
                                        ->default(now()),
                                    DatePicker::make('document_date')
                                        ->default(now()),
                                    DatePicker::make('due_date')
                                        ->required(),
                                ]),
                            ]),

                        Tab::make('Addresses')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                ])->schema([
                                    Section::make('Buy-from Address')
                                        ->schema([
                                            TextInput::make('vendor_address')
                                                ->label('Address'),
                                            TextInput::make('buy_from_city'),
                                            TextInput::make('buy_from_post_code'),
                                            TextInput::make('buy_from_contact'),
                                        ])->columnSpan(1),

                                    Section::make('Pay-to Address')
                                        ->schema([
                                            TextInput::make('pay_to_name'),
                                            TextInput::make('pay_to_address'),
                                            TextInput::make('pay_to_city'),
                                            TextInput::make('pay_to_post_code'),
                                            TextInput::make('pay_to_contact'),
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
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'xl' => 3,
                                ])->schema([
                                    Toggle::make('posted')
                                        ->disabled(),
                                    DateTimePicker::make('posted_at')
                                        ->disabled(),
                                    Select::make('posted_by')
                                        ->relationship('poster', 'name')
                                        ->disabled(),
                                    TextInput::make('location_code'),
                                    Select::make('receiving_location_id')
                                        ->relationship('location', 'name'),
                                    TextInput::make('package_tracking_no'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
