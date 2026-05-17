<?php

namespace App\Filament\Resources\ItemUomAssignments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItemUomAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Item No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('item.description')
                    ->label('Description')
                    ->searchable()
                    ->limit(35),

                TextColumn::make('uom.uom_code')
                    ->label('UOM Code')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('uom_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'BASE' => 'success',
                        'SALES' => 'info',
                        'PURCHASE' => 'warning',
                        'SHIPPING' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'BASE' => 'Base/Inventory',
                        'SALES' => 'Sales',
                        'PURCHASE' => 'Purchase',
                        'SHIPPING' => 'Shipping',
                        'REPORTING' => 'Reporting',
                        'ALTERNATE' => 'Alternate',
                        default => $state,
                    }),

                TextColumn::make('conversion_factor')
                    ->label('Conversion Factor')
                    ->numeric(6)
                    ->alignEnd(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Sort')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup('item.item_code') // Essential for clear ERP grouping of conversions
            ->filters([
                SelectFilter::make('uom_type')
                    ->options([
                        'BASE' => 'Base/Inventory',
                        'SALES' => 'Sales',
                        'PURCHASE' => 'Purchase',
                        'SHIPPING' => 'Shipping',
                        'REPORTING' => 'Reporting',
                        'ALTERNATE' => 'Alternate',
                    ]),

                TernaryFilter::make('is_default')
                    ->label('Is Default Unit'),
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
