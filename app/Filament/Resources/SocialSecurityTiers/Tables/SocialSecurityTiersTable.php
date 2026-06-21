<?php

namespace App\Filament\Resources\SocialSecurityTiers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SocialSecurityTiersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tier_code')
                    ->label('Tier')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('from_salary')
                    ->label('Min Salary')
                    ->money()
                    ->sortable(),

                TextColumn::make('to_salary')
                    ->label('Max Salary')
                    ->money()
                    ->placeholder('Unlimited')
                    ->sortable(),

                TextColumn::make('employee_rate')
                    ->label('Emp. Rate')
                    ->suffix('%')
                    ->color('info')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('employer_rate')
                    ->label('Empr. Rate')
                    ->suffix('%')
                    ->color('warning')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('max_base')
                    ->label('Max Base')
                    ->money()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('employee_max_amount')
                    ->label('Emp. Cap')
                    ->money()
                    ->toggleable(),

                TextColumn::make('employer_max_amount')
                    ->label('Empr. Cap')
                    ->money()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('from_salary', 'asc')
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
