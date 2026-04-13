<?php

declare(strict_types=1);

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
                TextColumn::make('acquisitionCostAccount.name')
                    ->label('Acquisition Account')
                    ->description(fn ($record) => $record->acquisitionCostAccount?->account_number)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('accumulatedDepreciationAccount.name')
                    ->label('Accum. Depr. Account')
                    ->description(fn ($record) => $record->accumulatedDepreciationAccount?->account_number)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('depreciationExpenseAccount.name')
                    ->label('Depr. Expense Account')
                    ->description(fn ($record) => $record->depreciationExpenseAccount?->account_number)
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('depreciation_calculation')
                    ->label('Calc. Method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'full_year' => 'Full Year',
                        'pro_rata' => 'Pro Rata',
                        'half_year' => 'Half Year',
                        default => $state,
                    })
                    ->toggleable(),

                // Disposal (Toggleable)
                TextColumn::make('disposalGainAccount.name')
                    ->label('Gain on Disposal')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('disposalLossAccount.name')
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
