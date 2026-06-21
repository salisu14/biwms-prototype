<?php

namespace App\Filament\Resources\SalesQuoteRevisions\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class SalesQuoteRevisionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Main Content Area
                Group::make()
                    ->schema([
                        Section::make('Revision Details')
                            ->description('Specific details regarding this quote update.')
                            ->schema([
                                TextEntry::make('revision_number')
                                    ->label('Revision Reference')
                                    ->weight(FontWeight::Bold)
                                    ->copyable(),

                                TextEntry::make('description')
                                    ->label('Change Description')
                                    ->placeholder('No description provided for this revision.')
                                    ->columnSpanFull(),
                            ])->columns(1),

                        Section::make('Data Changes')
                            ->description('The specific fields and values that were modified in this version.')
                            ->schema([
                                // Using KeyValueEntry to beautifully render the 'changes' JSON array
                                KeyValueEntry::make('changes')
                                    ->label(false)
                                    ->keyLabel('Field Name')
                                    ->valueLabel('New Value')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpan(2),

                // Sidebar / Metadata Area
                Group::make()
                    ->schema([
                        Section::make('Context')
                            ->schema([
                                TextEntry::make('salesQuote.quote_no')
                                    ->label('Parent Quote')
                                    ->color('primary')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('version')
                                    ->label('Version Number')
                                    ->prefix('v')
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('revision_date')
                                    ->label('Revision Date')
                                    ->dateTime('M j, Y g:i A'),
                            ]),

                        Section::make('System Audit')
                            ->collapsed()
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Logged At')
                                    ->dateTime()
                                    ->size(TextSize::Small),

                                TextEntry::make('updated_at')
                                    ->label('Last Modified')
                                    ->dateTime()
                                    ->size(TextSize::Small),

                                TextEntry::make('deleted_at')
                                    ->label('Archived At')
                                    ->dateTime()
                                    ->visible(fn ($record) => $record->trashed())
                                    ->color('danger'),
                            ]),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
