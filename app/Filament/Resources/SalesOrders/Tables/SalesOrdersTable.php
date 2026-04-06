<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('external_document_number')
                    ->searchable(),
                TextColumn::make('order_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->searchable(),
                TextColumn::make('customer_address')
                    ->searchable(),
                TextColumn::make('ship_to_name')
                    ->searchable(),
                TextColumn::make('ship_to_address')
                    ->searchable(),
                TextColumn::make('generalBusinessPostingGroup.id')
                    ->searchable(),
                TextColumn::make('customerPostingGroup.id')
                    ->searchable(),
                TextColumn::make('vat_bus_posting_group')
                    ->searchable(),
                TextColumn::make('pricingGroup.name')
                    ->searchable(),
                TextColumn::make('location.name')
                    ->searchable(),
                TextColumn::make('shipping_agent_code')
                    ->searchable(),
                TextColumn::make('shipping_agent_service_code')
                    ->searchable(),
                TextColumn::make('shipping_method')
                    ->badge()
                    ->searchable(),
                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('requested_delivery_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('promised_delivery_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('shipment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_terms_code')
                    ->searchable(),
                TextColumn::make('payment_method_code')
                    ->searchable(),
                TextColumn::make('subtotal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('line_discount_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('invoice_discount_percent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('invoice_discount_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_vat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency_code')
                    ->searchable(),
                TextColumn::make('currency_factor')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('quantity_shipped')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('quantity_invoiced')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('fully_shipped')
                    ->boolean(),
                IconColumn::make('fully_invoiced')
                    ->boolean(),
                TextColumn::make('salesperson.name')
                    ->searchable(),
                TextColumn::make('assigned_warehouse_worker_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('approved_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cancelled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('cancelled_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cancellation_reason')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
