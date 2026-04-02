<?php

namespace App\Filament\Resources\Items\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_number')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('generalProductPostingGroup.id')
                    ->searchable(),
                TextColumn::make('inventoryPostingGroup.id')
                    ->searchable(),
                TextColumn::make('vat_prod_posting_group')
                    ->searchable(),
                TextColumn::make('item_type')
                    ->searchable(),
                TextColumn::make('costing_method')
                    ->searchable(),
                TextColumn::make('unit_cost')
                    ->money()
                    ->sortable(),
                TextColumn::make('standard_cost')
                    ->money()
                    ->sortable(),
                TextColumn::make('last_direct_cost')
                    ->money()
                    ->sortable(),
                TextColumn::make('price_calculation_method')
                    ->searchable(),
                TextColumn::make('profit_percent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('default_price_list_code')
                    ->searchable(),
                IconColumn::make('allow_negative_price')
                    ->boolean(),
                TextColumn::make('unit_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('inventory')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reorder_point')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reorder_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->searchable(),
                TextColumn::make('bin_code')
                    ->searchable(),
                TextColumn::make('base_unit_of_measure')
                    ->searchable(),
                TextColumn::make('weight')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('volume')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('shelf_no')
                    ->searchable(),
                TextColumn::make('item_tracking_code')
                    ->searchable(),
                TextColumn::make('shelf_life_days')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('blocked')
                    ->boolean(),
                IconColumn::make('sales_blocked')
                    ->boolean(),
                IconColumn::make('purchasing_blocked')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
