<?php

namespace App\Filament\Resources\Locations\Tables;

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
                IconColumn::make('directed_put_away_and_pick')
                    ->label('WMS')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('bin_mandatory')
                    ->label('Bins')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('receipt_bin_code')
                    ->label('Rec. Bin')
                    ->toggleable(),
                IconColumn::make('blocked')
                    ->boolean()
                    ->color('danger')
                    ->sortable(),
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
