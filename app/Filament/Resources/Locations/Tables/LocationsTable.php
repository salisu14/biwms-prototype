<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Enums\LocationType;
use App\Enums\TemperatureZone;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => LocationType::tryFrom($state)?->label() ?? $state)
                    ->color(fn ($state) => match (LocationType::tryFrom($state)) {
                        LocationType::APPROVED => 'success',
                        LocationType::QUARANTINE => 'danger',
                        LocationType::RECEIVING => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('temperature_zone')
                    ->badge()
                    ->formatStateUsing(fn ($state) => TemperatureZone::tryFrom($state)?->label() ?? $state)
                    ->color('info')
                    ->toggleable(),
                IconColumn::make('directed_put_away_and_pick')
                    ->label('WMS')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                IconColumn::make('blocked')
                    ->boolean()
                    ->color('danger')
                    ->sortable()
                    ->toggleable(),
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
