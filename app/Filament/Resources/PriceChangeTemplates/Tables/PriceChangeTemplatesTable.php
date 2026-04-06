<?php

namespace App\Filament\Resources\PriceChangeTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PriceChangeTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'warning',
                        'applied' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('adjustment_type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('value')
                    ->label('Adj. Value')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($record, $state) => $record->adjustment_type === 'percentage' ? $state . '%' : '₦' . number_format($state, 2)),

                TextColumn::make('base')
                    ->label('Base')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('effective_from')
                    ->label('Active From')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                        'applied' => 'Applied',
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
