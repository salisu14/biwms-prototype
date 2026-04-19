<?php

namespace App\Filament\Resources\ApprovalTemplates\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApprovalTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Workflow Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->weight('bold'),

                        TextEntry::make('document_type')
                            ->badge()
                            ->color('info'),

                        IconEntry::make('enabled')
                            ->label('Enabled')
                            ->boolean(),

                        TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Constraints & Thresholds')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('amount_limit')
                            ->label('Trigger Amount')
                            ->money()
                            ->placeholder('No Limit'),

                        TextEntry::make('location_filter')
                            ->label('Location Scope')
                            ->placeholder('Global'),

                        TextEntry::make('due_date_formula')
                            ->label('SLA Days')
                            ->suffix(' Days'),
                    ]),

                Section::make('Advanced Filtering')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('vendorPostingGroup.code')
                            ->label('Vendor Posting Group')
                            ->placeholder('Any'),

                        TextEntry::make('dimension_1_filter')
                            ->label('Department Restrictions')
                            ->listWithLineBreaks()
                            ->placeholder('No specific departments'),

                        TextEntry::make('dimension_2_filter')
                            ->label('Project Restrictions')
                            ->listWithLineBreaks()
                            ->placeholder('No specific projects'),
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
