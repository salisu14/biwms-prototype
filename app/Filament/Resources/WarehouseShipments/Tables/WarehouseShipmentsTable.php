<?php

namespace App\Filament\Resources\WarehouseShipments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehouseShipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Shipment No.')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'OPEN' => 'gray',
                        'RELEASED' => 'info',
                        'PARTIALLY_SHIPPED' => 'warning',
                        'SHIPPED' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->description(fn ($record) => "Ref: {$record->source_document_number}")
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shipping_agent_code')
                    ->label('Agent')
                    ->description(fn ($record) => $record->shipping_agent_service_code)
                    ->toggleable(),

                TextColumn::make('shipment_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('planned_delivery_date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('posted_date')
                    ->label('Posted At')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'OPEN' => 'Open',
                        'RELEASED' => 'Released',
                        'PARTIALLY_SHIPPED' => 'Partially Shipped',
                        'SHIPPED' => 'Shipped',
                    ]),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name'),
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
