<?php

namespace App\Filament\Resources\ExpenseTransactions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class GlEntryRelationManager extends RelationManager
{
    // FIX: Changed from 'glEntry' to 'glEntries' to match the model method
    protected static string $relationship = 'glEntries';

    protected static ?string $title = 'G/L Entries';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_number')
                    ->label('Entry No.')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('chartOfAccount.name')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record): string => $record->chartOfAccount?->account_number ?? 'No Account'),

                TextColumn::make('description')
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('debit_amount')
                    ->label('Debit')
                    ->money('NGN')
                    ->alignment('right')
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('credit_amount')
                    ->label('Credit')
                    ->money('NGN')
                    ->alignment('right')
                    ->sortable()
                    ->color('success'),

                IconColumn::make('reconciled')
                    ->label('Rec')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn ($record): string =>
                    $record->reconciled
                        ? "Reconciled: " . $record->reconciliation_date?->format('Y-m-d')
                        : 'Unreconciled'
                    ),
            ])
            ->defaultSort('entry_number', 'desc')
            ->recordActions([
                // ViewAction::make(), // Ensure you import ViewAction if you enable this
            ])
            ->headerActions([
                // No Create action needed here
            ]);
    }
}
