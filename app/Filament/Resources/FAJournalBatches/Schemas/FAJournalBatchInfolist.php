<?php

namespace App\Filament\Resources\FAJournalBatches\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FAJournalBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('template.name')
                            ->label('Journal Type')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('name')
                            ->label('Batch Identifier')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'gray',
                                'released' => 'info',
                                'posted' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Configuration')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('depreciationBook.code')
                            ->label('Depreciation Book')
                            ->icon('heroicon-m-book-open'),

                        TextEntry::make('posting_date')
                            ->label('Reference Posting Date')
                            ->date(),

                        IconEntry::make('calculate_depreciation')
                            ->label('Auto-Calculate')
                            ->boolean(),

                        TextEntry::make('assignedUser.name')
                            ->label('Assigned Specialist')
                            ->icon('heroicon-m-user')
                            ->placeholder('Unassigned'),

                        TextEntry::make('description')
                            ->columnSpan(2)
                            ->placeholder('No description provided'),
                    ]),

                Section::make('Audit Trail')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
