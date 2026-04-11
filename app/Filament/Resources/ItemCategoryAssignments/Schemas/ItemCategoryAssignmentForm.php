<?php

namespace App\Filament\Resources\ItemCategoryAssignments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemCategoryAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment Details')
                    ->description('Link an item to a specific category.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('item_id')
                                ->label('Item')
                                ->relationship('item', 'item_code')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_code} - {$record->description}")
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('category_id')
                                ->label('Category')
                                ->relationship('category', 'category_name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "[{$record->category_code}] {$record->category_name}")
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),

                        Grid::make(3)->schema([
                            Toggle::make('is_primary')
                                ->label('Primary Category')
                                ->helperText('Mark this as the main category for the item.')
                                ->inline(false)
                                ->required(),

                            TextInput::make('sort_order')
                                ->label('Display Order')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
