<?php

namespace App\Filament\Resources\NumberSeries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NumberSeriesTable
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
                    ->wrap(),

                TextColumn::make('module')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('current_formatted')
                    ->label('Next Expected No.')
                    ->getStateUsing(fn ($record) => $record->getNextNumber() ?? 'Exhausted')
                    ->fontFamily('mono')
                    ->color('primary')
                    ->weight('bold'),

                TextColumn::make('range')
                    ->label('Range (Start - End)')
                    ->getStateUsing(fn ($record) => "{$record->starting_number} → " . ($record->ending_number ?? '∞'))
                    ->color('gray'),

                TextColumn::make('year')
                    ->label('Fiscal Year')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('allow_manual')
                    ->label('Manual')
                    ->boolean()
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
