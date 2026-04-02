<?php

namespace App\Filament\Resources\CustomerPostingGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerPostingGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('receivablesAccount.name')
                    ->searchable(),
                TextColumn::make('payment_disc_debit_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('payment_disc_credit_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('invoice_rounding_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('debit_rounding_account_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('credit_rounding_account_id')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('blocked')
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
