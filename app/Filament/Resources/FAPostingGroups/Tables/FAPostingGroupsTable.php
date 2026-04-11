<?php

namespace App\Filament\Resources\FAPostingGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FAPostingGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('acquisition_cost_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('acquisition_cost_offset_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('depreciation_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('depreciation_expense_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('maintenance_expense_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('maintenance_cost_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('disposal_proceeds_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('gain_on_disposal_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('loss_on_disposal_account_id')
                    ->numeric()
                    ->sortable(),
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
                TextColumn::make('appreciation_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('revaluation_gain_account_id')
                    ->numeric()
                    ->sortable(),
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
