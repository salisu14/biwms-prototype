<?php

namespace App\Filament\Resources\FAPostingGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FAPostingGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Posting Group Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),

                // Primary Accounts
                TextColumn::make('acquisitionAccount.name')
                    ->label('Acquisition Account')
                    ->description(fn ($record) => $record->acquisitionAccount?->account_number)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('depreciationAccount.name')
                    ->label('Accum. Depr. Account')
                    ->description(fn ($record) => $record->depreciationAccount?->account_number)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('depExpenseAccount.name')
                    ->label('Depr. Expense Account')
                    ->description(fn ($record) => $record->depExpenseAccount?->account_number)
                    ->toggleable()
                    ->searchable(),

                // Applicability Indicators
                TextColumn::make('applicable_tangible_types')
                    ->label('Tangible Types')
                    ->badge()
                    ->separator(',')
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('applicable_intangible_types')
                    ->label('Intangible Types')
                    ->badge()
                    ->separator(',')
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Disposal & Maintenance (Toggleable)
                TextColumn::make('maintenance_expense_account_id')
                    ->label('Maint. Expense A/C')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gainOnDisposalAccount.name')
                    ->label('Gain on Disposal')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('lossOnDisposalAccount.name')
                    ->label('Loss on Disposal')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
