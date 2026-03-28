<?php

namespace App\Filament\Resources\InventoryPostingSetups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryPostingSetupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('inventory_account')
                    ->searchable(),
                TextColumn::make('inventory_adjmt_account')
                    ->searchable(),
                TextColumn::make('invt_accrual_account')
                    ->searchable(),
                TextColumn::make('cogs_account')
                    ->searchable(),
                TextColumn::make('direct_cost_applied_account')
                    ->searchable(),
                TextColumn::make('overhead_applied_account')
                    ->searchable(),
                TextColumn::make('purchase_variance_account')
                    ->searchable(),
                TextColumn::make('material_variance_account')
                    ->searchable(),
                TextColumn::make('capacity_variance_account')
                    ->searchable(),
                TextColumn::make('subcontracted_variance_account')
                    ->searchable(),
                TextColumn::make('cap_overhead_variance_account')
                    ->searchable(),
                TextColumn::make('mfg_overhead_variance_account')
                    ->searchable(),
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
