<?php

namespace App\Filament\Resources\ProductionJournalBatches\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionJournalBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('template.name')
                            ->label('Template')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('name')
                            ->label('Batch Identifier')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Section::make('Operational Context')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('productionOrder.document_number')
                            ->label('Target Production Order')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->placeholder('Global/Multi-order Batch'),

                        TextEntry::make('assignedUser.name')
                            ->label('Responsible Person')
                            ->icon('heroicon-m-user')
                            ->placeholder('Unassigned'),

                        TextEntry::make('reason_code')
                            ->label('Reason Code')
                            ->placeholder('-'),

                        IconEntry::make('auto_post_on_release')
                            ->label('Background Posting')
                            ->boolean(),

                        TextEntry::make('dimension_filter')
                            ->label('Dimension Scope')
                            ->badge()
                            ->placeholder('No filters applied'),
                    ]),

                Section::make('Audit Information')
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
