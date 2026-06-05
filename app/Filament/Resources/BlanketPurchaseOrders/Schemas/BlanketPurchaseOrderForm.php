<?php

namespace App\Filament\Resources\BlanketPurchaseOrders\Schemas;

use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Vendor;
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

class BlanketPurchaseOrderForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Blanket Order')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-document-duplicate')
                            ->schema([
                                Grid::make(3)->schema([
                                    static::makeSystemGeneratedTextInput(
                                        'document_number',
                                        'BO Number',
                                        'Generated automatically when the blanket purchase order is created and cannot be changed.'
                                    )->maxLength(50),

                                    TextInput::make('status')
                                        ->default('OPEN')
                                        ->disabled()
                                        ->dehydrated(),
                                    TextInput::make('external_document_no')
                                        ->label('External Doc No.'),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('vendor_id')
                                        ->relationship('vendor', 'vendor_name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($state, Set $set) => self::updateVendorDetails($state, $set)),
                                    Select::make('buyer_id')
                                        ->relationship('buyer', 'name')
                                        ->searchable(),
                                    Select::make('assigned_user_id')
                                        ->label('Assigned User')
                                        ->relationship('assignedUser', 'name')
                                        ->searchable(),
                                    TextInput::make('currency_code')
                                        ->default('USD'),
                                ]),
                            ]),

                        Tab::make('Vendor & Addresses')
                            ->icon('heroicon-m-map-pin')
                            ->schema([
                                Section::make('Buy-from Vendor')
                                    ->schema([
                                        TextInput::make('buy_from_vendor_name')->label('Name'),
                                        TextInput::make('buy_from_address')->label('Address'),
                                        Grid::make(2)->schema([
                                            TextInput::make('buy_from_city')->label('City'),
                                            TextInput::make('buy_from_post_code')->label('Post Code'),
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
                                    TextInput::make('purchase_order_no')
                                        ->label('linked PO No.')
                                        ->disabled(),
                                ]),
                                Textarea::make('comment')->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    protected static function updateVendorDetails($state, Set $set): void
    {
        if (! $state) {
            return;
        }

        $vendor = Vendor::find($state);
        if ($vendor) {
            $set('buy_from_vendor_name', $vendor->vendor_name);
            $set('buy_from_address', $vendor->address);
            $set('buy_from_city', $vendor->city);
            $set('buy_from_post_code', $vendor->postal_code);
            $set('pay_to_vendor_no', $vendor->vendor_code);
            $set('currency_code', $vendor->currency);
        }
    }
}
