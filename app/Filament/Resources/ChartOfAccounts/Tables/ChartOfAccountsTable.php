<?php

namespace App\Filament\Resources\ChartOfAccounts\Tables;

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ChartOfAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_number')
                    ->label('Account No.')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Account Name')
                    ->searchable()
                    // Visualize hierarchy using indentation
                    ->formatStateUsing(fn($record, $state) => str_repeat('  ', $record->indentation) . $state)
                    ->color(fn($record) => $record->structural_type !== AccountStructuralType::POSTING ? 'primary' : null)
                    ->weight(fn($record) => $record->bold ? 'bold' : 'normal'),

                TextColumn::make('income_balance')
                    ->label('Statement')
                    ->badge()
                    ->color(fn($state) => $state === IncomeBalanceType::BALANCE_SHEET ? 'gray' : 'info')
                    ->toggleable(),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('NGN')
                    ->alignment('right')
                    ->weight('bold')
                    ->color(fn($state) => $state < 0 ? 'danger' : null)
                    ->sortable(),

                IconColumn::make('direct_posting')
                    ->label('Direct')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('blocked')
                    ->boolean()
                    ->trueColor('danger')
                    ->alignCenter(),
            ])
            ->defaultSort('account_number')
            ->filters([
                SelectFilter::make('account_category')
                    ->options(AccountCategory::class),
                SelectFilter::make('structural_type')
                    ->options(AccountStructuralType::class),
                TernaryFilter::make('income_balance')
                    ->label('Statement Type')
                    ->placeholder('All Accounts')
                    ->trueLabel('Income Statement')
                    ->falseLabel('Balance Sheet')
                    ->queries(
                        true: fn($query) => $query->where('income_balance', IncomeBalanceType::INCOME_STATEMENT),
                        false: fn($query) => $query->where('income_balance', IncomeBalanceType::BALANCE_SHEET),
                    ),
                TernaryFilter::make('blocked')
                    ->label('Blocked Status'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
