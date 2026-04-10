<?php

namespace App\Filament\Resources\Dimensions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DimensionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code_caption')
                    ->label('Code Caption')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('filter_caption')
                    ->label('Filter Caption')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dimension_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'global' => 'success',
                        'shortcut' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('global_dimension_no')
                    ->label('Global No.')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('blocked')
                    ->label('Blocked Status'),
                SelectFilter::make('dimension_type')
                    ->options([
                        'global' => 'Global',
                        'shortcut' => 'Shortcut',
                        'regular' => 'Regular',
                    ]),
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
