<?php

namespace App\Filament\Resources\UnitOfMeasures\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitOfMeasureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Unit Identity')
                    ->description('Primary identification and classification for this Unit of Measure.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('uom_code')
                            ->label('UOM Code')
                            ->weight('bold')
                            ->color('primary')
                            ->extraAttributes(['class' => 'uppercase']),

                        TextEntry::make('uom_category')
                            ->label('Category')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'WEIGHT' => 'info',
                                'VOLUME' => 'warning',
                                'LENGTH' => 'success',
                                'PIECE' => 'primary',
                                default => 'gray',
                            })
                            ->placeholder('UNCLASSIFIED'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('No description provided.'),
                    ]),

                Grid::make(2)->schema([
                    Section::make('System Rules')
                        ->description('Global rules governing this unit of measure.')
                        ->columnSpan(1)
                        ->schema([
                            IconEntry::make('is_base_uom')
                                ->label('Is Global Base Unit')
                                ->boolean()
                                ->trueColor('success')
                                ->falseColor('gray')
                                ->helperText('If enabled, this acts as a core base measurement unit across associated items.'),
                        ]),

                    Section::make('Audit Trail')
                        ->description('Database registration and modification history.')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Created On')
                                ->dateTime()
                                ->placeholder('-'),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime()
                                ->placeholder('-'),
                        ]),
                ]),
            ]);
    }
}
