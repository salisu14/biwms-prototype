<?php

namespace App\Filament\Resources\ItemCategoryAssignments\Schemas;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Items\ItemResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemCategoryAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('item.item_code')
                            ->label('Item')
                            ->weight('bold')
                            ->formatStateUsing(fn ($state, $record): string => $record->item
                                ? "{$record->item->item_code} - {$record->item->description}"
                                : '—')
                            ->url(fn ($record): ?string => $record->item
                                ? ItemResource::getUrl('view', ['record' => $record->item])
                                : null),

                        TextEntry::make('category.category_name')
                            ->label('Category')
                            ->weight('bold')
                            ->formatStateUsing(fn ($state, $record): string => $record->category
                                ? "[{$record->category->category_code}] {$record->category->category_name}"
                                : '—')
                            ->url(fn ($record): ?string => $record->category
                                ? CategoryResource::getUrl('view', ['record' => $record->category])
                                : null),

                        IconEntry::make('is_primary')
                            ->label('Primary')
                            ->boolean(),

                        TextEntry::make('sort_order')
                            ->label('Sort Order')
                            ->numeric(),
                    ]),
            ]);
    }
}
