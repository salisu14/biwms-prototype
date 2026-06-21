<?php

namespace App\Filament\Resources\FAJournalTemplates\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FAJournalTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Template')
                            ->weight('bold'),

                        TextEntry::make('template_type')
                            ->badge()
                            ->color('info'),

                        IconEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean(),

                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided.'),
                    ]),

                Section::make('Workflow & Posting')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('numberSeries.code')
                            ->label('No. Series'),

                        TextEntry::make('postingNumberSeries.code')
                            ->label('Posting Series')
                            ->placeholder('Standard'),

                        TextEntry::make('source_code')
                            ->label('Source Code')
                            ->placeholder('-'),

                        TextEntry::make('defaultDepreciationBook.code')
                            ->label('Default Depr. Book')
                            ->icon('heroicon-m-book-open'),

                        IconEntry::make('test_report_before_posting')
                            ->label('Mandatory Test Report')
                            ->boolean(),
                    ]),

                Section::make('Audit Trail')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
