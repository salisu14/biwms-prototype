<?php

namespace App\Filament\Resources\InventoryPutaways\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryPutawaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->label('Number')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Open' => 'gray',
                        'Pending' => 'warning',
                        'Completed' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('source_document')
                    ->label('Source Type')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('source_no')
                    ->label('Source No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned')
                    ->sortable(),

                TextColumn::make('posting_date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Open' => 'Open',
                        'Pending' => 'Pending',
                        'Completed' => 'Completed',
                    ]),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
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
