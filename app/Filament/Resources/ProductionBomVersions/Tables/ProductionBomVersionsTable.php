<?php

namespace App\Filament\Resources\ProductionBomVersions\Tables;

use App\Enums\ProductionBomStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionBomVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productionBom.description')
                    ->label('Production BOM')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('version_code')
                    ->label('Version')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('starting_date')
                    ->label('Starts')
                    ->date()
                    ->sortable(),

                TextColumn::make('ending_date')
                    ->label('Ends')
                    ->date()
                    ->sortable()
                    ->placeholder('Open ended'),

                TextColumn::make('unit_of_measure_code')
                    ->label('UOM')
                    ->alignCenter(),

                TextColumn::make('quantity_per')
                    ->label('Qty Per')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('cost_rollup')
                    ->label('Cost')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductionBomStatus::class),
                SelectFilter::make('production_bom_id')
                    ->relationship('productionBom', 'description')
                    ->label('BOM')
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
