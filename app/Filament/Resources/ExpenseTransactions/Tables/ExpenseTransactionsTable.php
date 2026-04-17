<?php

namespace App\Filament\Resources\ExpenseTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpenseTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_no')
                    ->label('Doc No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money()
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('amount_lcy')
                    ->label('Total (LCY)')
                    ->money()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('account_type')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'posted' => 'success',
                        'reversed' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('invoice_no')
                    ->label('Inv No.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('posting_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'posted' => 'Posted',
                        'reversed' => 'Reversed',
                    ]),
                SelectFilter::make('document_type')
                    ->options([
                        'invoice' => 'Invoice',
                        'credit_memo' => 'Credit Memo',
                    ]),
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
