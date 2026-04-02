<?php

namespace App\Filament\Resources\Routings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoutingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->weight('bold'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'SERIAL' => 'primary',
                                'PARALLEL' => 'success',
                                default => 'gray',
                            }),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'DRAFT' => 'gray',
                                'CERTIFIED' => 'success',
                                'ARCHIVED' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Item Association')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('item.item_number')
                                    ->label('Item')
                                    ->badge()
                                    ->placeholder('-'),

                                TextEntry::make('item.description')
                                    ->label('Item Description')
                                    ->limit(50)
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make('Costing & Dates')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('cost_rollup')
                            ->label('Cost Rollup')
                            ->money('USD'),

                        TextEntry::make('starting_date')
                            ->label('Valid From')
                            ->date(),

                        TextEntry::make('ending_date')
                            ->label('Valid Until')
                            ->date(),
                    ])
                    ->collapsible(),

                Section::make('Audit Trail')
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
                    ->collapsible(),
            ]);
    }
}
