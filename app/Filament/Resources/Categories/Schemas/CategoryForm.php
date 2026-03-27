<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\CategoryType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Basic details about the category.')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('category_code')
                                ->label('Category Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(50)
                                ->columnSpan(1),

                            TextInput::make('category_name')
                                ->label('Category Name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(1),

                            Select::make('category_type')
                                ->label('Type')
                                ->options(
                                    collect(CategoryType::cases())
                                        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                )
                                ->required()
                                ->default('THERAPEUTIC')
                                ->live()
                                ->columnSpan(1),
                        ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Placeholder::make('dynamic_description')
                            ->label('Type Information')
                            ->content(fn ($get): string =>
                                CategoryType::tryFrom($get('category_type'))?->description() ?? 'Select a type to see details.'
                            )
                            ->columnSpanFull(),
                    ]),

                Section::make('Hierarchy')
                    ->description('Structure and classification settings.')
                    ->icon('heroicon-o-squares-2x2')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('parent_id')
                                ->label('Parent Category')
                                ->options(function () {
                                    return \App\Models\Category::query()
                                        ->whereNotNull('category_name')
                                        ->where('category_name', '!=', '')
                                        ->get()
                                        ->mapWithKeys(fn ($category) => [
                                            $category->id => $category->category_name ?? 'Unnamed Category (ID: ' . $category->id . ')'
                                        ])
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->columnSpan(1),

                            TextInput::make('level')
                                ->label('Hierarchy Level')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->hint('0 = Root, 1 = Child, etc.')
                                ->columnSpan(1),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('hierarchy_path')
                                ->label('Hierarchy Path')
                                ->required()
                                ->helperText('e.g. ROOT.THERAPEUTIC.IMMUNE')
                                ->columnSpan(1),

                            TextInput::make('sort_order')
                                ->label('Sort Order')
                                ->numeric()
                                ->default(0)
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('Settings & Attributes')
                    ->description('Additional configuration and metadata.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible()
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true)
                            ->inline(false)
                            ->onColor('success')
                            ->offColor('danger'),

                        Textarea::make('attributes')
                            ->label('Attributes (JSON)')
                            ->rows(5)
                            ->helperText('Enter flexible attributes as a JSON object.'),
                    ]),
            ]);
    }
}
