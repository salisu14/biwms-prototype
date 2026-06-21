<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryAdjustmentJournalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)->schema([
                    Section::make('General Information')
                        ->columnSpan(8)
                        ->columns(2)
                        ->schema([
                            TextEntry::make('journal_batch_name')
                                ->label('Batch Name')
                                ->weight('bold')
                                ->color('primary')
                                ->icon('heroicon-m-tag'),

                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Open' => 'gray',
                                    'Released' => 'warning',
                                    'Posted' => 'success',
                                    default => 'gray',
                                })
                                ->icon('heroicon-m-check-circle'),

                            TextEntry::make('description')
                                ->columnSpanFull()
                                ->placeholder('No description provided.'),
                        ]),

                    Section::make('Timeline')
                        ->columnSpan(4)
                        ->schema([
                            TextEntry::make('posting_date')
                                ->date()
                                ->icon('heroicon-m-calendar'),

                            TextEntry::make('document_date')
                                ->date()
                                ->color('gray')
                                ->icon('heroicon-m-document-text'),
                        ]),

                    Section::make('Inventory Context')
                        ->description('The warehouse and business reasoning for these adjustments.')
                        ->columnSpan(12)
                        ->columns(3)
                        ->schema([
                            TextEntry::make('location.name')
                                ->label('Location')
                                ->weight('medium')
                                ->icon('heroicon-m-map-pin'),

                            TextEntry::make('reasonCode.description')
                                ->label('Reason')
                                ->placeholder('No Reason Code')
                                ->icon('heroicon-m-question-mark-circle'),

                            TextEntry::make('assignedUser.name')
                                ->label('Assigned To')
                                ->placeholder('Unassigned')
                                ->icon('heroicon-m-user'),
                        ]),

                    Section::make('Posting & Audit')
                        ->description('Historical data captured upon journal completion.')
                        ->columnSpan(12)
                        ->columns(2)
                        ->visible(fn ($record) => $record?->status === 'Posted')
                        ->schema([
                            TextEntry::make('posted_by')
                                ->label('Posted By')
                                ->formatStateUsing(fn ($state) => User::find($state)?->name ?? 'System')
                                ->weight('medium')
                                ->icon('heroicon-m-user-circle'),

                            TextEntry::make('posted_at')
                                ->label('Posting Timestamp')
                                ->dateTime()
                                ->color('gray')
                                ->icon('heroicon-m-clock'),
                        ]),

                    Section::make('Metadata')
                        ->collapsed()
                        ->columnSpan(12)
                        ->columns(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->dateTime()
                                ->label('Date Created'),
                            TextEntry::make('updated_at')
                                ->dateTime()
                                ->label('Last Modified'),
                        ]),
                ]),
            ]);
    }
}
