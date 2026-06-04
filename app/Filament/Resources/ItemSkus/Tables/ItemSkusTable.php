<?php

namespace App\Filament\Resources\ItemSkus\Tables;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Locations\LocationResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItemSkusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sku_code')
            ->columns([
                TextColumn::make('sku_code')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn ($record): string => $record->item ? "{$record->item->item_code} - {$record->item->description}" : '')
                    ->description(fn ($record): string => $record->item?->description ?? '')
                    ->formatStateUsing(fn ($state, $record): string => $record->item
                        ? "{$record->item->item_code} - {$record->item->description}"
                        : '—')
                    ->url(fn ($record): ?string => $record->item
                        ? ItemResource::getUrl('view', ['record' => $record->item])
                        : null)
                    ->limit(30),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state, $record): string => $record->location
                        ? "{$record->location->code} - {$record->location->name}"
                        : '—')
                    ->url(fn ($record): ?string => $record->location
                        ? LocationResource::getUrl('view', ['record' => $record->location])
                        : null),

                TextColumn::make('current_quantity')
                    ->label('On Hand')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record): string => $record->needs_reorder ? 'danger' : 'success'
                    )
                    ->suffix(' qty'),

                IconColumn::make('needs_reorder')
                    ->label('Reorder')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn ($record): string => $record->needs_reorder
                        ? "Below Reorder Point ({$record->reorder_point})"
                        : 'Stock Sufficient'
                    ),

                TextColumn::make('lead_time_days')
                    ->label('Lead Time')
                    ->numeric()
                    ->suffix(' days')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reorder_point')
                    ->label('Reorder Point')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('safety_stock')
                    ->label('Safety Stock')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'item_code')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'location_name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('needs_reorder')
                    ->label('Needs Reorder')
                    ->placeholder('All SKUs')
                    ->trueLabel('Low Stock Only')
                    ->falseLabel('Sufficient Stock'),

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All SKUs')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
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
