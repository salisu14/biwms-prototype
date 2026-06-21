<?php

namespace App\Filament\Resources\PurchasePrices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchasePricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->vendor?->vendor_code ?? ''),
                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->item?->description ?? ''),
                TextColumn::make('direct_unit_cost')
                    ->label('Direct Unit Cost')
                    ->money(config('app.default_currency', 'USD'))
                    ->sortable(),
                TextColumn::make('line_discount_percent')
                    ->label('Line Disc. %')
                    ->suffix('%')
                    ->sortable()
                    ->badge(),
                TextColumn::make('minimum_quantity')
                    ->label('Min Qty')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_of_measure_code')
                    ->label('UoM')
                    ->placeholder('Base')
                    ->sortable(),
                TextColumn::make('starting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('ending_date')
                    ->date()
                    ->sortable()
                    ->placeholder('Open'),
            ])
            ->filters([
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable(),
                SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'item_code')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
