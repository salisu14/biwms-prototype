<?php

namespace App\Filament\Resources\ReasonCodes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReasonCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->searchable()
                    ->wrap(),

                // Display how many times this reason code has been used
                TextColumn::make('journals_count')
                    ->counts('journals')
                    ->label('Usage Count')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('default_location_code')
                    ->label('Default Location')
                    ->toggleable(),

                TextColumn::make('inventory_adjustment_account')
                    ->label('Adj. Account')
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                IconColumn::make('blocked')
                    ->boolean()
                    ->label('Blocked'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                TernaryFilter::make('blocked')
                    ->label('Blocked'),
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
