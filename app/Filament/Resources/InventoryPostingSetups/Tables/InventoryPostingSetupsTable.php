<?php

namespace App\Filament\Resources\InventoryPostingSetups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryPostingSetupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location.code')
                    ->label('Loc. Code')
                    ->searchable()
                    ->sortable()
                    ->placeholder('DEFAULT')
                    ->weight('bold'),

                TextColumn::make('inventoryPostingGroup.code')
                    ->label('Inv. Posting Group')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('inventoryAccount.account_number')
                    ->label('Inventory Account')
                    ->description(fn ($record) => $record->inventoryAccount?->name)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('inventoryAccountInterim.account_number')
                    ->label('Interim Account')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('wipAccount.account_number')
                    ->label('WIP Account')
                    ->description(fn ($record) => $record->wipAccount?->name)
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('inventory_posting_group_id')
                    ->label('Posting Group')
                    ->relationship('inventoryPostingGroup', 'code')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])->defaultSort('inventory_posting_group_id', 'asc');
    }
}
