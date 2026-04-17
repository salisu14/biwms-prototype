<?php

namespace App\Filament\Resources\TaxTable\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxTablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Table Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('jurisdiction')
                    ->label('Jurisdiction')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('country_code')
                    ->label('Country')
                    ->searchable(),

                TextColumn::make('effective_date')
                    ->label('Effective From')
                    ->date()
                    ->sortable(),

                TextColumn::make('brackets_count')
                    ->label('Brackets')
                    ->counts('brackets')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
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
