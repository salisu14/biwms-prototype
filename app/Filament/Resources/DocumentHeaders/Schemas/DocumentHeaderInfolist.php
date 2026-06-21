<?php

namespace App\Filament\Resources\DocumentHeaders\Schemas;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentHeaderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Header Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('doc_type')
                            ->label('Type')
                            ->formatStateUsing(fn ($state): string => DocumentType::tryFrom($state)?->label() ?? $state)
                            ->color(fn ($state): string => DocumentType::tryFrom($state)?->color() ?? 'gray'),
                        //                            ->icon(fn ($state): ?string => DocumentType::tryFrom($state)?->icon()),

                        TextEntry::make('doc_no')
                            ->label('Document Number')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn ($state): string => DocumentStatus::tryFrom($state)?->label() ?? $state)
                            ->color(fn ($state): string => DocumentStatus::tryFrom($state)?->color() ?? 'gray'),
                        //                            ->icon(fn ($state): ?string => DocumentStatus::tryFrom($state)?->icon()),

                        TextEntry::make('creator.name')
                            ->label('Created By'),

                        TextEntry::make('doc_date')
                            ->label('Document Date')
                            ->date(),

                        TextEntry::make('posting_date')
                            ->label('Posting Date')
                            ->date(),
                    ]),

                Section::make('Financial Summary')
                    ->description('Calculated values based on associated ledger lines.')
                    ->schema([
                        TextEntry::make('ledgerEntries_count')
                            ->label('Number of Lines')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('total_value')
                            ->label('Total Document Value')
                            ->money('USD')
                            ->size('text-xl')
                            ->weight('bold')
                            ->color('primary'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('No notes provided.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created At'),

                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->label('Last Updated'),
                    ])
                    ->collapsible(),
            ]);
    }
}
