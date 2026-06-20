<?php

namespace App\Filament\Resources\Routings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoutingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('type')
                    ->badge()
                    ->label('Type')
                    ->color(fn (string $state): string => match ($state) {
                        'SERIAL' => 'primary',
                        'PARALLEL' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'CERTIFIED' => 'success',
                        'ARCHIVED' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('item.item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('item.description')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('cost_rollup')
                    ->label('Cost Rollup')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('starting_date')
                    ->label('Valid From')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ending_date')
                    ->label('Valid Until')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'SERIAL' => 'Serial',
                        'PARALLEL' => 'Parallel',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'CERTIFIED' => 'Certified',
                        'ARCHIVED' => 'Archived',
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

    /**
     * Helper to get the next operation number
     */
    protected static function getNextOperationNumber(int $maxSoFar): int
    {
        return $maxSoFar + 10000;
    }
}
