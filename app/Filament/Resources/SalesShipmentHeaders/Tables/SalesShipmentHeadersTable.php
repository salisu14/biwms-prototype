<?php

namespace App\Filament\Resources\SalesShipmentHeaders\Tables;

use App\Enums\ShipmentStatus;
use App\Models\Location;
use App\Models\SalesShipmentHeader;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class SalesShipmentHeadersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_no')
                    ->label('No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sell_to_customer_name')
                    ->label('Customer Name')
                    ->searchable(),
                TextColumn::make('sell_to_customer_no')
                    ->label('Customer No.')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (ShipmentStatus $state): string => match ($state) {
                        ShipmentStatus::SHIPPED => 'success',
                        ShipmentStatus::PARTIALLY_SHIPPED => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('order_no')
                    ->label('Order No.')
                    ->searchable(),
                TextColumn::make('location_code')
                    ->sortable(),
                TextColumn::make('shipment_date')
                    ->date()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('location_code')
                    ->options(fn () => Location::pluck('code', 'code')),
                Filter::make('shipped_not_invoiced')
                    ->query(fn (Builder $query): Builder => $query->shippedNotInvoiced())
                    ->label('Shipped Not Invoiced'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('print_waybill')
                    ->label('Print Waybill')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (SalesShipmentHeader $record) => route('waybill.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
