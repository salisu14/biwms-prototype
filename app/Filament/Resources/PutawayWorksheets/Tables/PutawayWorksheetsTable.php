<?php

namespace App\Filament\Resources\PutawayWorksheets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PutawayWorksheetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('worksheet_number')
                    ->label('Worksheet No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.name')
                    ->label('Assigned To')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Open' => 'gray',
                        'Released' => 'info',
                        'In Progress' => 'warning',
                        'Completed' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('lines_count')
                    ->label('Lines')
                    ->counts('lines')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
                SelectFilter::make('status')
                    ->options([
                        'Open' => 'Open',
                        'Released' => 'Released',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
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
