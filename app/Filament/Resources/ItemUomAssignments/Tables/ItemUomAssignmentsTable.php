<?php

namespace App\Filament\Resources\ItemUomAssignments\Tables;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\UnitOfMeasures\UnitOfMeasureResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, $record): string => $record->item
                        ? "{$record->item->item_code} - {$record->item->description}"
                        : '—')
                    ->url(fn ($record): ?string => $record->item
                        ? ItemResource::getUrl('view', ['record' => $record->item])
                        : null)
                    ->description(fn ($record): string => $record->item?->description ?? ''),

                TextColumn::make('uom.uom_code')
                    ->label('UoM')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->formatStateUsing(fn ($state, $record): string => $record->uom
                        ? "{$record->uom->uom_code} - {$record->uom->description}"
                        : '—')
                    ->url(fn ($record): ?string => $record->uom
                        ? UnitOfMeasureResource::getUrl('view', ['record' => $record->uom])
                        : null),

                TextColumn::make('uom_type')
                    ->label('Scope')
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
                    ->label('Qty. per UoM')
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
            ->defaultGroup('item.item_code')
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Delete Selected'),
                ]),
            ]);
    }
}
