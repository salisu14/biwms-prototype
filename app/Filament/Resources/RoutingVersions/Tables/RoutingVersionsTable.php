<?php

namespace App\Filament\Resources\RoutingVersions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoutingVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('routing.code')
                    ->label('Routing Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('version_code')
                    ->label('Version')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'UNDER_DEVELOPMENT' => 'warning',
                        'CERTIFIED' => 'success',
                        'CLOSED' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title()),

                TextColumn::make('type')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('starting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('ending_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->ending_date && $record->ending_date->isPast() ? 'danger' : null),

                TextColumn::make('cost_rollup')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'UNDER_DEVELOPMENT' => 'Development',
                        'CERTIFIED' => 'Certified',
                        'CLOSED' => 'Closed',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'SERIAL' => 'Serial',
                        'PARALLEL' => 'Parallel',
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
