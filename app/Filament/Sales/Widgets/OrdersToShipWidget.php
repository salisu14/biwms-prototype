<?php

namespace App\Filament\Sales\Widgets;

use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OrdersToShipWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Orders Due to Ship';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesOrder::query()
                    ->whereIn('status', ['RELEASED', 'PICKING', 'PACKED', 'PARTIALLY_SHIPPED'])
                    ->where('requested_delivery_date', '<=', now()->addDays(7))
                    ->orderBy('requested_delivery_date')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('requested_delivery_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state < now() ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'RELEASED' => 'warning',
                        'PICKING' => 'info',
                        'PACKED' => 'info',
                        'PARTIALLY_SHIPPED' => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (SalesOrder $record): string => route('filament.sales.resources.sales-orders.edit', $record))
                    ->icon('heroicon-m-eye'),
            ]);
    }
}
