<?php

namespace App\Filament\Resources\GeneralJournalBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralJournalBatchInfolist
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

                        TextEntry::make('assignedUser.name')
                            ->label('Assigned User')
                            ->placeholder('—'),

                        TextEntry::make('balancingAccount.name')
                            ->label('Balancing Account')
                            ->placeholder('—'),

                        TextEntry::make('reason_code')
                            ->label('Reason Code')
                            ->placeholder('—'),
                    ]),

                Section::make('Financial Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('total_debits')
                            ->label('Total Debits')
                            ->state(fn ($record) => $record->totalDebits())
                            ->numeric(decimalPlaces: 2)
                            ->color('success'),

                        TextEntry::make('total_credits')
                            ->label('Total Credits')
                            ->state(fn ($record) => $record->totalCredits())
                            ->numeric(decimalPlaces: 2)
                            ->color('danger'),

                        TextEntry::make('is_balanced')
                            ->label('Balanced?')
                            ->state(fn ($record) => $record->isBalanced() ? 'Yes ✓' : 'No — out of balance')
                            ->color(fn ($record) => $record->isBalanced() ? 'success' : 'danger'),
                    ]),

                Section::make('Date Restrictions')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('posting_date_restriction_from')
                            ->label('Allowed From')
                            ->date()
                            ->placeholder('No restriction'),

                        TextEntry::make('posting_date_restriction_to')
                            ->label('Allowed To')
                            ->date()
                            ->placeholder('No restriction'),
                    ]),
            ]);
    }
}
