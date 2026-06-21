<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\CategoryType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Section::make('Category Details')
                        ->schema([
                            TextEntry::make('category_code')
                                ->label('Code')
                                ->inlineLabel(),

                            TextEntry::make('category_name')
                                ->label('Name')
                                ->inlineLabel()
                                ->weight('bold'),

                            TextEntry::make('category_type')
                                ->label('Type')
                                ->inlineLabel()
                                ->badge(),
                            //                                ->color(fn ($state): string => CategoryType::tryFrom($state)?->color() ?? 'gray')
                            //                                ->icon(fn ($state): ?string => CategoryType::tryFrom($state)?->icon())
                            //                                ->formatStateUsing(fn ($state): string => CategoryType::tryFrom($state)?->label() ?? $state),

                            TextEntry::make('description')
                                ->label('Description')
                                ->inlineLabel()
                                ->columnSpanFull()
                                ->placeholder('No description provided.'),
                        ])
                        ->columnSpan(1),

                    Section::make('Hierarchy & Settings')
                        ->schema([
                            TextEntry::make('parent.category_name')
                                ->label('Parent Category')
                                ->inlineLabel()
                                ->placeholder('Root Level'),

                            TextEntry::make('level')
                                ->label('Hierarchy Level')
                                ->inlineLabel()
                                ->formatStateUsing(fn ($state) => 'Level '.$state),

                            TextEntry::make('hierarchy_path')
                                ->label('Path')
                                ->inlineLabel()
                                ->copyable()
                                ->placeholder('-'),

                            TextEntry::make('sort_order')
                                ->label('Sort Order')
                                ->inlineLabel(),

                            IconEntry::make('is_active')
                                ->label('Active')
                                ->inlineLabel()
                                ->boolean(),
                        ])
                        ->columnSpan(1),
                ]),

                Section::make('Attributes Metadata')
                    ->schema([
                        TextEntry::make('attributes')
                            ->label('JSON Attributes')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
