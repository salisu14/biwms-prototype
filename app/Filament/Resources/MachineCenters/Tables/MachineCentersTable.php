<?php

namespace App\Filament\Resources\MachineCenters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MachineCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('workCenter.name')
                    ->searchable(),
                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('efficiency')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('direct_unit_cost')
                    ->money()
                    ->sortable(),
                TextColumn::make('indirect_cost_percent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('overhead_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('setup_time')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('wait_time')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('move_time')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('location_code')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
