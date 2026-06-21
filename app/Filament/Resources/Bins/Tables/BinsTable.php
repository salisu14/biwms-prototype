<?php

namespace App\Filament\Resources\Bins\Tables;

use App\Enums\BinType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BinsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bin_code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('zone.zone_code')
                    ->label('Zone')
                    ->description(fn ($record) => $record->location?->name)
                    ->sortable(),

                TextColumn::make('bin_type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('maximum_weight')
                    ->label('Max Weight')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' kg')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('dedicated')
                    ->label('Ded.')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('block_movement_in')
                    ->label('In-Block')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->toggleable(),

                IconColumn::make('block_movement_out')
                    ->label('Out-Block')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->toggleable(),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('bin_code')
            ->filters([
                SelectFilter::make('location_id')
                    ->relationship('location', 'name')
                    ->label('Location'),
                SelectFilter::make('zone_id')
                    ->relationship('zone', 'zone_code')
                    ->label('Zone'),
                SelectFilter::make('bin_type')
                    ->options(BinType::class),
                TernaryFilter::make('blocked')
                    ->label('Is Blocked'),
                TernaryFilter::make('dedicated')
                    ->label('Dedicated Bins Only'),
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
