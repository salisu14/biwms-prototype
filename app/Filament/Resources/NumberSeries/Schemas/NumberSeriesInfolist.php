<?php

namespace App\Filament\Resources\NumberSeries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NumberSeriesInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Configuration')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('description')
                            ->columnSpan(2),
                        TextEntry::make('module')
                            ->badge(),
                        IconEntry::make('is_active')
                            ->label('Currently Active')
                            ->boolean(),
                        IconEntry::make('allow_manual')
                            ->label('Manual Override Permitted')
                            ->boolean(),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Sequence Details')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('prefix')
                                ->label('Formatted Prefix'),
                            TextEntry::make('year')
                                ->label('Fiscal Tracking Year'),
                            TextEntry::make('next_number_preview')
                                ->label('Next Issued Number')
                                ->getStateUsing(fn ($record) => $record->getNextNumber())
                                ->fontFamily('mono')
                                ->color('primary')
                                ->weight('bold'),
                        ]),

                    Section::make('Numeric Range')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('starting_number')
                                ->label('Starting at'),
                            TextEntry::make('current_number')
                                ->label('Last number used'),
                            TextEntry::make('ending_number')
                                ->label('Threshold / End')
                                ->placeholder('No end limit'),
                        ]),
                ]),

                Section::make('System Metadata')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
