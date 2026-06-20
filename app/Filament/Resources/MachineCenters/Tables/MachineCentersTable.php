<?php

namespace App\Filament\Resources\MachineCenters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MachineCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Machine Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('workCenter.name')
                    ->label('Work Center')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('capacity')
                    ->numeric()
                    ->suffix(' U/h')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('efficiency')
                    ->numeric()
                    ->suffix('%')
                    ->sortable()
                    ->color(fn ($state) => $state < 90 ? 'warning' : 'success')
                    ->alignEnd(),

                TextColumn::make('location_code')
                    ->label('Location')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                // Financials and timings moved to infoList/hidden by default for a cleaner UI
                TextColumn::make('direct_unit_cost')
                    ->money('NGN')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('setup_time')
                    ->label('Setup')
                    ->numeric()
                    ->suffix('m')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('work_center_id')
                    ->relationship('workCenter', 'name')
                    ->label('Work Center')
                    ->searchable()
                    ->preload(),
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
