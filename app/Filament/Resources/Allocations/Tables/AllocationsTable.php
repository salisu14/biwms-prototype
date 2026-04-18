<?php

namespace App\Filament\Resources\Allocations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AllocationsTable
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

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('total_percentage')
                    ->label('Total %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => (float)$state === 100.00 ? 'success' : 'warning')
                    ->sortable(),

                TextColumn::make('lines_count')
                    ->label('Destinations')
                    ->counts('lines')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]) ->defaultSort('code', 'asc');
    }
}
