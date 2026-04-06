<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Models\SalesOrder;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('order_number'),
                TextEntry::make('external_document_number')
                    ->placeholder('-'),
                TextEntry::make('order_type')
                    ->badge(),
                TextEntry::make('customer.name')
                    ->label('Customer'),
                TextEntry::make('customer_name'),
                TextEntry::make('customer_address')
                    ->placeholder('-'),
                TextEntry::make('ship_to_name')
                    ->placeholder('-'),
                TextEntry::make('ship_to_address')
                    ->placeholder('-'),
                TextEntry::make('generalBusinessPostingGroup.id')
                    ->label('General business posting group')
                    ->placeholder('-'),
                TextEntry::make('customerPostingGroup.id')
                    ->label('Customer posting group')
                    ->placeholder('-'),
                TextEntry::make('vat_bus_posting_group')
                    ->placeholder('-'),
                TextEntry::make('pricingGroup.name')
                    ->label('Pricing group')
                    ->placeholder('-'),
                TextEntry::make('location.name')
                    ->label('Location')
                    ->placeholder('-'),
                TextEntry::make('shipping_agent_code')
                    ->placeholder('-'),
                TextEntry::make('shipping_agent_service_code')
                    ->placeholder('-'),
                TextEntry::make('shipping_method')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('order_date')
                    ->date(),
                TextEntry::make('posting_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('requested_delivery_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('promised_delivery_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('shipment_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('payment_terms_code')
                    ->placeholder('-'),
                TextEntry::make('payment_method_code')
                    ->placeholder('-'),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('line_discount_total')
                    ->numeric(),
                TextEntry::make('invoice_discount_percent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('invoice_discount_amount')
                    ->numeric(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('total_vat')
                    ->numeric(),
                TextEntry::make('grand_total')
                    ->numeric(),
                TextEntry::make('currency_code'),
                TextEntry::make('currency_factor')
                    ->numeric(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('quantity_shipped')
                    ->numeric(),
                TextEntry::make('quantity_invoiced')
                    ->numeric(),
                IconEntry::make('fully_shipped')
                    ->boolean(),
                IconEntry::make('fully_invoiced')
                    ->boolean(),
                TextEntry::make('salesperson.name')
                    ->label('Salesperson')
                    ->placeholder('-'),
                TextEntry::make('assigned_warehouse_worker_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('approved_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->numeric(),
                TextEntry::make('cancelled_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('cancelled_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('cancellation_reason')
                    ->placeholder('-'),
                TextEntry::make('internal_comment')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('customer_comment')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (SalesOrder $record): bool => $record->trashed()),
            ]);
    }
}
