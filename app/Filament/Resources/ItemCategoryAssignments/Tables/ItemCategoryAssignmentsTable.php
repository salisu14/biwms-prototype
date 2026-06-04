<?php

namespace App\Filament\Resources\ItemCategoryAssignments\Tables;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Items\ItemResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

                TextColumn::make('category.category_name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->formatStateUsing(fn ($state, $record): string => $record->category
                        ? "[{$record->category->category_code}] {$record->category->category_name}"
                        : '—')
                    ->url(fn ($record): ?string => $record->category
                        ? CategoryResource::getUrl('view', ['record' => $record->category])
                        : null)
                    ->description(fn ($record): string => $record->category?->category_code ?? ''),

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
            ->defaultSort('item.item_code')
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
