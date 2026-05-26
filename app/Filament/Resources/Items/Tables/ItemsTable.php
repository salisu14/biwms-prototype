<?php

namespace App\Filament\Resources\Items\Tables;

use App\Enums\CostingMethod;
use App\Enums\ItemType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('primaryCategory.category_name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable(['description', 'description_2'])
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description_2 ?? $record->description),

                TextColumn::make('item_type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('ledger_on_hand')
                    ->label('On Hand')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'success'),

                TextColumn::make('qty_on_sales_order')
                    ->label('On Sales Order')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                    ->color('warning')
                    ->toggleable(),

                TextColumn::make('qty_on_purchase_order')
                    ->label('On Purchase Order')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('available_to_promise')
                    ->label('Available')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->toggleable(),

                TextColumn::make('stock_alert')
                    ->label('Stock Alert')
                    ->badge()
                    ->state(function ($record): string {
                        if ((float) $record->available_to_promise <= 0) {
                            return 'Sold Out';
                        }

                        if ((bool) $record->needs_reorder) {
                            return 'Reorder Needed';
                        }

                        return 'In Stock';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Sold Out' => 'danger',
                        'Reorder Needed' => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('uoms.uom_code')
                    ->label('Base UoM')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('unit_price')
                    ->label('Price')
                    ->money('NGN', locale: 'ng')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label('Cost')
                    ->money('NGN')
                    ->alignRight()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('costing_method')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('inventoryPostingGroup.description')
                    ->label('Inv. Posting Group')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('location.name')
                    ->label('Default Location')
                    ->placeholder('N/A')
                    ->toggleable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                IconColumn::make('blocked')
                    ->boolean()
                    ->label('Blocked')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->columnManagerLayout(ColumnManagerLayout::Modal)
            ->filters([
                SelectFilter::make('item_type')
                    ->options(ItemType::class),
                SelectFilter::make('costing_method')
                    ->options(CostingMethod::class),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
                TernaryFilter::make('blocked')
                    ->label('Is Blocked'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])->defaultSort('item_code', 'asc');
    }
}
