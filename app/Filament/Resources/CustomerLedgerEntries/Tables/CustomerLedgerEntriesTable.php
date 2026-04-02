<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerLedgerEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->searchable(),
                TextColumn::make('document_type')
                    ->searchable(),
                TextColumn::make('document_number')
                    ->searchable(),
                TextColumn::make('external_document_number')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('document_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('debit_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('credit_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('running_balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('remaining_amount')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('open')
                    ->boolean(),
                IconColumn::make('fully_applied')
                    ->boolean(),
                TextColumn::make('currency_code')
                    ->searchable(),
                TextColumn::make('original_debit_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('original_credit_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency_factor')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('generalBusinessPostingGroup.id')
                    ->searchable(),
                TextColumn::make('customerPostingGroup.id')
                    ->searchable(),
                TextColumn::make('glEntry.id')
                    ->searchable(),
                TextColumn::make('source_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source_type')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('reversed')
                    ->boolean(),
                TextColumn::make('reversed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reversed_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reversal_entry_number')
                    ->searchable(),
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
