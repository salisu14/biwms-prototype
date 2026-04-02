<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_code')
                    ->searchable(),
                TextColumn::make('account_name')
                    ->searchable(),
                TextColumn::make('bank_name')
                    ->searchable(),
                TextColumn::make('bank_branch')
                    ->searchable(),
                TextColumn::make('account_number')
                    ->searchable(),
                TextColumn::make('routing_number')
                    ->searchable(),
                TextColumn::make('swift_code')
                    ->searchable(),
                TextColumn::make('iban')
                    ->searchable(),
                TextColumn::make('glAccount.name')
                    ->searchable(),
                TextColumn::make('currency_code')
                    ->searchable(),
                TextColumn::make('account_type')
                    ->searchable(),
                TextColumn::make('current_balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('available_balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_reconciliation_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('last_reconciliation_balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('next_check_number')
                    ->searchable(),
                TextColumn::make('check_form_id')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                IconColumn::make('allow_payments')
                    ->boolean(),
                IconColumn::make('allow_receipts')
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
