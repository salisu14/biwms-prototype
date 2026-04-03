<?php

namespace App\Filament\Resources\ProductionBoms\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionBomInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->weight('bold'),

                        TextEntry::make('description')
                            ->columnSpanFull(),

                        TextEntry::make('status')
                            ->badge()
                            ->label('Status')
                            ->color(fn (string $state): string => match ($state) {
                                'UNDER_DEVELOPMENT' => 'gray',
                                'CERTIFIED' => 'success',
                                'CLOSED' => 'danger',
                                default => 'productivity', // Fallback
                            }),

                        TextEntry::make('item.item_number')
                            ->label('Item')
                            ->badge()
                            ->color('gray')
                            ->placeholder('-'),
                    ]),

                Section::make('Type & Versioning')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('item.item_number')
                            ->label('Item Number')
                            ->badge()
                            ->color('primary')
                            ->placeholder('-'),

                        TextEntry::make('type.name')
                            ->label('Type')
                            ->badge(),
//                            ->color(fn (string $state): string => ProductionBomType::from($state)?->color() ?? 'gray'),

                        TextEntry::make('version')
                            ->label('Version Count')
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Financials')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cost_rollup')
                            ->label('Cost Rollup')
                            ->money('money')
                            ->placeholder('-'),

                        TextEntry::make('item.unit_cost')
                        ->label('Item Standard Cost')
                        ->money()
                        ->placeholder('-'),
                ]),

                Section::make('Audit Trail')
                    ->schema([
                    TextEntry::make('created_at')
                        ->label('Created At')
                        ->dateTime()
                        ->placeholder('-'),

                    TextEntry::make('updated_at')
                        ->label('Last Updated')
                        ->dateTime()
                        ->placeholder('-'),
                ])
                ->collapsible(),
            ]);
    }
}
