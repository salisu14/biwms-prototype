<?php

namespace App\Filament\Resources\MachineCenters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MachineCenterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->icon('heroicon-m-cpu-chip')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Machine Code')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('name')
                                    ->label('Name'),
                                TextEntry::make('workCenter.name')
                                    ->label('Parent Work Center')
                                    ->badge(),
                                TextEntry::make('location_code')
                                    ->label('Location')
                                    ->placeholder('No specific location'),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Production Metrics')
                            ->icon('heroicon-m-bolt')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('capacity')
                                    ->numeric()
                                    ->suffix(' Units/Hour'),
                                TextEntry::make('efficiency')
                                    ->numeric()
                                    ->suffix('%'),
                            ]),

                        Section::make('Timing Parameters')
                            ->icon('heroicon-m-clock')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('setup_time')->label('Setup')->suffix('m'),
                                        TextEntry::make('wait_time')->label('Wait')->suffix('m'),
                                        TextEntry::make('move_time')->label('Move')->suffix('m'),
                                    ]),
                            ]),
                    ]),

                Section::make('Costing Structure')
                    ->icon('heroicon-m-banknotes')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('direct_unit_cost')
                                    ->money()
                                    ->color('success'),
                                TextEntry::make('overhead_rate')
                                    ->money(),
                                TextEntry::make('indirect_cost_percent')
                                    ->numeric()
                                    ->suffix('%'),
                            ]),
                    ]),

                Section::make('Audit Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')->dateTime()->color('gray'),
                                TextEntry::make('updated_at')->dateTime()->color('gray'),
                            ]),
                    ])
                    ->compact()
            ]);
    }
}
