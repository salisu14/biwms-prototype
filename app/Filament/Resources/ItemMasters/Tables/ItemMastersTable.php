<?php

namespace App\Filament\Resources\ItemMasters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemMastersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description),

                TextColumn::make('item_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-'),

                TextColumn::make('inventory_method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-'),

                // FIXED: M2M categories - show primary or list
                TextColumn::make('categories.category_name')
                    ->label('Categories')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),

                // FIXED: Get base UOM via relationship method
                TextColumn::make('base_uom')
                    ->label('Base UOM')
                    ->state(fn ($record) => $record->getDefaultUom(\App\Enums\UomType::BASE)?->uom_code ?? '-'),

                // FIXED: Use accessor from model
                TextColumn::make('current_standard_cost')
                    ->label('Std Cost')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('reference_price')
                    ->label('Ref Price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('shelf_life_days')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
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
