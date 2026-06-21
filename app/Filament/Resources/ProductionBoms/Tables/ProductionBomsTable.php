<?php

namespace App\Filament\Resources\ProductionBoms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionBomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'CERTIFIED' => 'success',
                        'UNDER_DEVELOPMENT' => 'warning',
                        'CLOSED' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('item.item_code')
                    ->label('Item #')
                    ->sortable(),

                TextColumn::make('item.description')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('version')
                    ->label('Ver.')
                    ->alignCenter(),

                TextColumn::make('cost_rollup')
                    ->money('USD')
                    ->label('Rollup Cost'),

                TextColumn::make('starting_date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'UNDER_DEVELOPMENT' => 'Under Development',
                        'CERTIFIED' => 'Certified',
                        'CLOSED' => 'Closed',
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
