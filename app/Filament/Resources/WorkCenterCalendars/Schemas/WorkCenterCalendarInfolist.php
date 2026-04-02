<?php

namespace App\Filament\Resources\WorkCenterCalendars\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkCenterCalendarInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shift Details')
                    ->description('General information about the work center schedule.')
                    ->icon('heroicon-m-calendar-days')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('workCenter.name')
                                    ->label('Work Center')
                                    ->weight('bold')
                                    ->columnSpan(2),

                                TextEntry::make('date')
                                    ->date('d/m/Y')
                                    ->icon('heroicon-m-calendar')
                                    ->columnSpan(1),
                            ]),

                        Grid::make(3)
                            ->schema([
                                IconEntry::make('is_working_day')
                                    ->label('Working Day')
                                    ->boolean()
                                    ->columnSpan(1),

                                TextEntry::make('start_time')
                                    ->label('Start Time')
                                    ->time('H:i')
                                    ->placeholder('-')
                                    ->visible(fn ($record) => $record->is_working_day)
                                    ->columnSpan(1),

                                TextEntry::make('end_time')
                                    ->label('End Time')
                                    ->time('H:i')
                                    ->placeholder('-')
                                    ->visible(fn ($record) => $record->is_working_day)
                                    ->columnSpan(1),

                                TextEntry::make('absence_code')
                                    ->label('Absence Reason')
                                    ->badge()
                                    ->color('danger')
                                    ->placeholder('N/A')
                                    ->hidden(fn ($record) => $record->is_working_day)
                                    ->columnSpan(2),
                            ]),
                    ]),

                Section::make('Performance Metrics')
                    ->description('Capacity and efficiency settings for this period.')
                    ->icon('heroicon-m-bolt')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('capacity')
                                    ->numeric()
                                    ->suffix(' Hours')
                                    ->color('primary'),

                                TextEntry::make('efficiency')
                                    ->numeric()
                                    ->suffix('%')
                                    ->color(fn ($state) => $state < 80 ? 'danger' : 'success'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('System Metadata')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->size('sm')
                                    ->color('gray'),

                                TextEntry::make('updated_at')
                                    ->dateTime()
                                    ->size('sm')
                                    ->color('gray'),
                            ]),
                    ])
                    ->compact()
                    ->collapsible(),
            ]);
    }
}
