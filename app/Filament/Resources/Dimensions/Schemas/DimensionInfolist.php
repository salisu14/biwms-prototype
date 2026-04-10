<?php

namespace App\Filament\Resources\Dimensions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DimensionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TOP SECTION: General Identity
                Section::make('General Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Dimension Code')
                                    ->weight('bold')
                                    ->size('lg'),

                                TextEntry::make('name')
                                    ->label('Name')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->columnSpan(2),

                                TextEntry::make('description')
                                    ->columnSpanFull()
                                    ->placeholder('No description provided.'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('dimension_type')
                                    ->badge()
                                    ->color('primary'),

                                IconEntry::make('blocked')
                                    ->label('Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-x-circle')
                                    ->trueColor('danger')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success'),

                                TextEntry::make('global_dimension_no')
                                    ->label('Priority')
                                    ->formatStateUsing(function ($state) {
                                        if ($state == 1) return 'Global Dimension 1';
                                        if ($state == 2) return 'Global Dimension 2';
                                        return 'Shortcut Dimension';
                                    })
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                            ]),
                    ]),

                // MIDDLE SECTION: Configuration
                Section::make('Configuration')
                    ->description('Labels and captions used in filtering and reporting.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('code_caption')
                                    ->label('Code Caption')
                                    ->placeholder('-'),

                                TextEntry::make('filter_caption')
                                    ->label('Filter Caption')
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->collapsible(),

                // BOTTOM SECTION: Audit Trail (Collapsed by default to save space)
                Section::make('System Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
