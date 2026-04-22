<?php

namespace App\Filament\Resources\RecurringJournalBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecurringJournalBatchInfolist
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
                                'processing' => 'warning',
                                'posted' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('assignedUser.name')
                            ->label('Assigned User')
                            ->placeholder('—'),

                        TextEntry::make('current_processing_date')
                            ->label('Processing Date')
                            ->date()
                            ->placeholder('Not set'),

                        TextEntry::make('lines_count')
                            ->label('Lines')
                            ->state(fn ($record) => $record->lines()->count()),
                    ]),

                Section::make('Template Schedule')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('template.recurring_method')
                            ->label('Recurring Method')
                            ->badge(),

                        TextEntry::make('template.recurring_frequency')
                            ->label('Frequency')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('template.next_posting_date')
                            ->label('Next Due')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('template.last_posting_date')
                            ->label('Last Posted')
                            ->dateTime()
                            ->placeholder('Never'),

                        TextEntry::make('template.start_date')
                            ->label('Start Date')
                            ->date(),

                        TextEntry::make('template.end_date')
                            ->label('End Date')
                            ->date()
                            ->placeholder('Open-ended'),
                    ]),
            ]);
    }
}
