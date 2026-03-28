<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')
                    ->required(),
                Select::make('order_type')
                    ->options(PurchaseOrderType::class)
                    ->default('purchase_order')
                    ->required(),
                Select::make('status')
                    ->options(PurchaseOrderStatus::class)
                    ->default('PENDING')
                    ->required(),
                Select::make('vendor_id')
                    ->relationship('vendor', 'id')
                    ->required(),
                TextInput::make('vendor_name')
                    ->required(),
                DatePicker::make('order_date')
                    ->required(),
                Select::make('location_id')
                    ->relationship('location', 'id')
                    ->required(),
                DatePicker::make('posting_date'),
                DatePicker::make('due_date'),
                DatePicker::make('delivery_date'),
                TextInput::make('payment_terms'),
                Textarea::make('comment')
                    ->columnSpanFull(),
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
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
                TextInput::make('approved_by')
                    ->numeric(),
                DateTimePicker::make('approved_at'),
            ]);
    }
}
