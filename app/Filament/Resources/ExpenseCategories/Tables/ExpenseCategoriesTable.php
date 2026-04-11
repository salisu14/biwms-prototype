<?php

namespace App\Filament\Resources\ExpenseCategories\Tables;

use App\Enums\AccountType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ExpenseCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category_code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->description(fn ($record) => $record->category_type === 'cogs' ? 'Cost of Sales' : $record->notes),

                TextColumn::make('account_type')
                    ->label('Class')
                    ->badge()
                    ->sortable(),

                TextColumn::make('category_type')
                    ->label('Category')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_direct')
                    ->label('Direct')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('is_variable')
                    ->label('Var.')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('expenseAccount.name')
                    ->label('G/L Account')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('account_type')
            ->filters([
                SelectFilter::make('account_type')
                    ->options(AccountType::class),
                SelectFilter::make('category_type')
                    ->options([
                        'expense' => 'Expense',
                        'revenue' => 'Revenue',
                        'cogs' => 'COGS',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
                TernaryFilter::make('is_direct')
                    ->label('Direct Costs Only'),
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
