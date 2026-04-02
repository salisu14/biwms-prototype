<?php

namespace App\Filament\Resources\WorkCenters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WorkCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('group.name')
                    ->label('Group')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subcontractor.vendor_name')
                    ->label('Subcontractor')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->sortable()
                    ->numeric(),

                IconColumn::make('efficiency')
                    ->label('Efficiency')
                    ->tooltip(fn ($record): string => "{$record->efficiency}%")
                    ->color(fn ($record): string => $record->efficiency >= 90 ? 'success' : ($record->efficiency >= 75 ? 'warning' : 'danger')),

                TextColumn::make('direct_unit_cost')
                    ->label('Direct Cost')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('subcontractor_id')
                    ->label('Outsourced')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-building-office')
                    ->tooltip(fn ($record): string => $record->subcontractor ? 'Subcontracted' : 'Internal'),

                TextColumn::make('location_code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('work_center_group_id')
                    ->label('Group')
                    ->relationship('group', 'name')
                    ->searchable(),

                TernaryFilter::make('subcontractor_id')
                    ->label('Type')
                    ->placeholder('All Centers')
                    ->trueLabel('Subcontractors')
                    ->falseLabel('Internal'),
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
