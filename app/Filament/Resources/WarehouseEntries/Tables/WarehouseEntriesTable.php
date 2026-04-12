<?php

namespace App\Filament\Resources\WarehouseEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehouseEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_timestamp')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('document_no')
                    ->label('Doc. No.')
                    ->searchable()
                    ->description(fn ($record) => $record->document_type),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->description(fn ($record) => $record->item?->description)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('entry_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'positive' => 'success',
                        'negative' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 4)
                    ->weight('bold')
                    ->color(fn ($record) => $record->isPositive() ? 'success' : 'danger')
                    ->alignment('right'),

                TextColumn::make('unit_of_measure_code')
                    ->label('UOM')
                    ->alignCenter(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->toggleable(),

                TextColumn::make('bin.bin_code')
                    ->label('Bin')
                    ->description(fn ($record) => $record->zone?->zone_code)
                    ->toggleable(),

                TextColumn::make('total_cost')
                    ->label('Cost (LCY)')
                    ->money()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignment('right'),

                TextColumn::make('lot_no')
                    ->label('Lot/Serial')
                    ->state(fn ($record) => $record->lot_no ?? $record->serial_no ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('entry_timestamp', 'desc')
            ->filters([
                SelectFilter::make('entry_type')
                    ->options([
                        'positive' => 'Positive Adjustment',
                        'negative' => 'Negative Adjustment',
                    ]),
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'name'),
                SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'description', fn ($query) => $query->orderBy('item_code'))
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
