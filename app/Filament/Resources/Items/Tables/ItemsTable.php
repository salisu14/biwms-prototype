<?php

namespace App\Filament\Resources\Items\Tables;

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

                TextColumn::make('description')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description),

                TextColumn::make('item_type')
                    ->badge()
                    ->formatStateUsing(fn (ItemType $state): string => $state->label())
                    ->color(fn (ItemType $state): string => $state->color())
                    ->icon(fn (ItemType $state): string => $state->icon())
                    ->sortable(),

                TextColumn::make('inventory')
                    ->numeric(decimalPlaces: 2)
                    ->label('Stock')
                    ->alignRight()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('unit_price')
                    ->money()
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->money()
                    ->alignRight()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('base_unit_of_measure')
                    ->label('UoM')
                    ->sortable()
                    ->toggleable(),

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
                    ->options(ItemType::options()),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
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
            ]);
    }
}
