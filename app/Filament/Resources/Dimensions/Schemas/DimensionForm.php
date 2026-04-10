<?php

namespace App\Filament\Resources\Dimensions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DimensionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dimension Identification')
                    ->description('Primary naming and codes for the financial dimension.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Dimension Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g., DEPT')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                            TextInput::make('name')
                                ->required()
                                ->placeholder('e.g., Department'),
                        ]),
                    ]),

                Section::make('Display & Captions')
                    ->description('Configure how this dimension appears in reports and filters.')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code_caption')
                                ->label('Code Caption')
                                ->placeholder('Defaults to "Code"'),
                            TextInput::make('filter_caption')
                                ->label('Filter Caption')
                                ->placeholder('Defaults to "Filter"'),
                        ]),
                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Technical Configuration')
                    ->description('Define the behavior and global hierarchy of the dimension.')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('dimension_type')
                                ->label('Type')
                                ->options([
                                    'global' => 'Global Dimension',
                                    'shortcut' => 'Shortcut Dimension',
                                    'regular' => 'Regular Dimension',
                                ])
                                ->default('regular')
                                ->required()
                                ->native(false),
                            TextInput::make('global_dimension_no')
                                ->label('Global No.')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(8)
                                ->helperText('Assign 1-8 for Global/Shortcut tracking.'),
                            Toggle::make('blocked')
                                ->label('Blocked')
                                ->helperText('Prevent usage in new transactions.')
                                ->inline(false),
                        ]),
                    ]),
            ]);
    }
}
