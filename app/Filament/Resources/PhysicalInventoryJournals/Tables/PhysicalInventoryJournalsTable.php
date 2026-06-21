<?php

namespace App\Filament\Resources\PhysicalInventoryJournals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhysicalInventoryJournalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('journal_batch_name')
                    ->label('Batch')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'Open',
                        'warning' => 'Counting',
                        'info' => 'Calculated',
                        'success' => 'Posted',
                    ]),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->sortable(),

                TextColumn::make('bin_code')
                    ->label('Bin')
                    ->toggleable(),

                TextColumn::make('sorting_method')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->toggleable(),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines')
                    ->badge(),

                TextColumn::make('updated_at')
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
            ]);
    }
}
