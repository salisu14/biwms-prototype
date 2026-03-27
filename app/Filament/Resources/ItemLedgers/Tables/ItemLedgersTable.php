<?php

namespace App\Filament\Resources\ItemLedgers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.id')
                    ->searchable(),
                TextColumn::make('location.id')
                    ->searchable(),
                TextColumn::make('doc_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uom.id')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('entry_type')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->money()
                    ->sortable(),
                TextColumn::make('balance_after')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cost_after')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lot_number')
                    ->searchable(),
                TextColumn::make('expiry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
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
