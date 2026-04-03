<?php

namespace App\Filament\Resources\RoutingVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoutingVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Section::make('Technical Details')
                            ->columnSpan(2)
                            ->schema([
                                TextEntry::make('routing.name')
                                    ->label('Routing Plan'),
                                TextEntry::make('version_code')
                                    ->label('Version Number'),
                                TextEntry::make('description')
                                    ->columnSpanFull()
                                    ->placeholder('No description provided.'),

                                Grid::make(2)->schema([
                                    TextEntry::make('starting_date')->date(),
                                    TextEntry::make('ending_date')->date(),
                                ]),
                            ]),

                        Section::make('Configuration')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'UNDER_DEVELOPMENT' => 'warning',
                                        'CERTIFIED' => 'success',
                                        'CLOSED' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('type')
                                    ->icon('heroicon-m-arrows-right-left'),
                                TextEntry::make('cost_rollup')
                                    ->money('USD'),
                                TextEntry::make('creator.name')
                                    ->label('Owner'),
                                TextEntry::make('lastModifier.name')
                                    ->label('Last Modified By'),
                            ]),
                    ]),
            ]);
    }
}
