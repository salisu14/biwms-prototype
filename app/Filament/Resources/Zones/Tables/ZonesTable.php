<?php

namespace App\Filament\Resources\Zones\Tables;

use App\Enums\ZoneType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zone_code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('zone_name')
                    ->label('Name')
                    ->searchable()
                    ->description(fn ($record) => $record->location?->name),

                TextColumn::make('zone_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('warehouse_class')
                    ->label('Class')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('max_weight')
                    ->label('Max Weight')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' kg')
                    ->alignment('right')
                    ->sortable(),

                IconColumn::make('bin_mandatory')
                    ->label('Bins Req.')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->trueColor('danger')
                    ->alignCenter(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('location_id')
                    ->relationship('location', 'name')
                    ->label('Location'),
                SelectFilter::make('zone_type')
                    ->options(ZoneType::class),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
                TernaryFilter::make('blocked')
                    ->label('Blocked Status'),
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
