<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->bank_name),
                TextColumn::make('account_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('account_number')
                    ->label('Account No.')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('currency.code')
                    ->label('Curr.')
                    ->alignCenter(),
                TextColumn::make('current_balance')
                    ->label('Balance')
                    ->money(fn ($record) => $record->currency?->code)
                    ->alignment('right')
                    ->sortable()
                    ->weight('bold'),
                IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->alignCenter(),

                // Fixed: Removed the Grid wrapper which is invalid in Table columns
                IconColumn::make('allow_payments')
                    ->label('AP')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter(),
                IconColumn::make('allow_receipts')
                    ->label('AR')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter(),

                TextColumn::make('last_reconciliation_date')
                    ->label('Last Recon.')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('glAccount.name')
                    ->label('G/L Account')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('account_code')
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active Accounts'),
                SelectFilter::make('account_type')
                    ->options([
                        'CHECKING' => 'Checking',
                        'SAVINGS' => 'Savings',
                        'CREDIT' => 'Credit Card',
                    ]),
                SelectFilter::make('currency_id')
                    ->label('Currency')
                    ->relationship('currency', 'code'),
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
