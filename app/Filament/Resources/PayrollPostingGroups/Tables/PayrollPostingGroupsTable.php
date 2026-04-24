<?php

namespace App\Filament\Resources\PayrollPostingGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollPostingGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Group Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('employees_count')
                    ->label('Active Employees')
                    ->counts('employees')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('salariesAccount.account_number')
                    ->label('Salaries Account')
                    ->description(fn ($record) => $record->salariesAccount?->name)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('netPayAccount.account_number')
                    ->label('Net Pay Account')
                    ->description(fn ($record) => $record->netPayAccount?->name)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
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
            ])->defaultSort('code', 'asc');
    }
}
