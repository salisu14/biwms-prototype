<?php

namespace App\Filament\Resources\WarehouseActivities\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity Identification')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('no')
                                ->label('Activity No.')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('activity_type')
                                ->badge(),
                            TextEntry::make('status')
                                ->badge()
                                ->color(fn ($state) => match ($state->value) {
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                    'in_progress' => 'warning',
                                    'released' => 'info',
                                    default => 'gray',
                                }),
                        ]),
                    ]),

                Section::make('Location & Ownership')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('location.name')
                                ->label('Site Location')
                                ->icon('heroicon-m-map-pin')
                                ->color('primary'),
                            TextEntry::make('assignedUser.name')
                                ->label('Assigned User')
                                ->icon('heroicon-m-user')
                                ->placeholder('Unassigned'),
                        ]),
                    ]),

                Section::make('Source Document Link')
                    ->description('Origin document that triggered this activity.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('source_document')->label('Document Type'),
                            TextEntry::make('source_no')->label('Document No.'),
                            TextEntry::make('source_line_no')->label('Source Line Reference'),
                        ]),
                    ]),

                Section::make('Timeline & Execution')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('started_at')->dateTime()->placeholder('Not started'),
                            TextEntry::make('completed_at')->dateTime()->placeholder('Incomplete'),
                            TextEntry::make('completed_at')
                                ->label('Work Duration')
                                ->state(fn ($record) => $record->started_at && $record->completed_at
                                    ? $record->started_at->diffForHumans($record->completed_at, true)
                                    : '-')
                                ->icon('heroicon-m-clock'),
                        ]),
                    ]),

                Section::make('Internal Notes')
                    ->schema([
                        TextEntry::make('remarks')
                            ->placeholder('No remarks recorded.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
