<?php

namespace App\Filament\Resources\ItemJournalBatches\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemJournalBatchInfolist
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

                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Section::make('Processing Defaults')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('assignedUser.name')
                            ->label('Responsible Specialist')
                            ->icon('heroicon-m-user'),

                        TextEntry::make('location.name')
                            ->label('Primary Location')
                            ->icon('heroicon-m-map-pin')
                            ->placeholder('Unspecified'),

                        TextEntry::make('default_entry_type')
                            ->label('Entry Logic')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('reason_code')
                            ->label('Reason Code')
                            ->placeholder('-'),

                        IconEntry::make('copy_item_dimensions')
                            ->label('Dim. Inheritance')
                            ->boolean(),
                    ]),

                Section::make('Audit Trail')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')->label('Batch Created')->dateTime(),
                        TextEntry::make('updated_at')->label('Last Modified')->dateTime(),
                    ]),
            ]);
    }
}
