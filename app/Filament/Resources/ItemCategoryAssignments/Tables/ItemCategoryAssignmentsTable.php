<?php

namespace App\Filament\Resources\ItemCategoryAssignments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItemCategoryAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.item_number')
                    ->label('Item No.')
                    ->description(fn ($record) => $record->item?->description)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.category_name')
                    ->label('Category')
                    ->description(fn ($record) => $record->category?->category_code)
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Assigned')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('item.item_number')
            ->filters([
                TernaryFilter::make('is_primary')
                    ->label('Primary Only'),

                SelectFilter::make('category_id')
                    ->label('Filter by Category')
                    ->relationship('category', 'category_name')
                    ->searchable()
                    ->preload(),
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
