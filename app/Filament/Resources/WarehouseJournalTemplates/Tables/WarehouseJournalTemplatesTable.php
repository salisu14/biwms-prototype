<?php

namespace App\Filament\Resources\WarehouseJournalTemplates\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WarehouseJournalTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('journal_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'movement' => 'info',
                        'pick' => 'warning',
                        'put_away' => 'success',
                        'physical_inventory' => 'gray',
                        'adjustment' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pick' => 'Pick',
                        'put_away' => 'Put-Away',
                        'movement' => 'Movement',
                        'physical_inventory' => 'Phys. Inventory',
                        'adjustment' => 'Adjustment',
                        default => $state,
                    }),

                IconColumn::make('bin_mandatory')
                    ->label('Bin')
                    ->boolean(),

                IconColumn::make('zone_mandatory')
                    ->label('Zone')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('item_tracking_mandatory')
                    ->label('Tracking')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('directed_put_away_and_pick')
                    ->label('Directed')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_physical_inventory')
                    ->label('Phys. Inv.')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('journal_type')
                    ->options([
                        'pick' => 'Pick',
                        'put_away' => 'Put-Away',
                        'movement' => 'Movement',
                        'physical_inventory' => 'Physical Inventory',
                        'adjustment' => 'Adjustment',
                    ])
                    ->native(false),
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('bin_mandatory')->label('Bin Mandatory'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
