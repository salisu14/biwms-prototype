<?php

namespace App\Filament\Resources\WarehouseJournalBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseJournalBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('template.name')
                            ->label('Template'),

                        TextEntry::make('name')
                            ->label('Batch Name')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state?->value ?? $state) {
                                'open' => 'info',
                                'released' => 'warning',
                                'posted' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('location.name')
                            ->label('Location'),

                        TextEntry::make('zone.code')
                            ->label('Zone Filter')
                            ->placeholder('All Zones'),

                        TextEntry::make('journal_type')
                            ->label('Journal Type')
                            ->badge()
                            ->color('gray')
                            ->placeholder('From Template'),

                        TextEntry::make('assignedUser.name')
                            ->label('Assigned To')
                            ->placeholder('—'),

                        TextEntry::make('lines_count')
                            ->label('Lines')
                            ->state(fn ($record) => $record->lines()->count()),

                        TextEntry::make('template.source_code')
                            ->label('Source Code')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
