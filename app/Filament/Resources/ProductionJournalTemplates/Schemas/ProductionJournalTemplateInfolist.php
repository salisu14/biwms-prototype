<?php

namespace App\Filament\Resources\ProductionJournalTemplates\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionJournalTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->weight('bold'),
                        TextEntry::make('journal_type')->badge()->color('info'),
                        IconEntry::make('is_active')->label('Status')->boolean(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('-'),
                    ]),

                Section::make('Logic & Automation')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('flushing_method_filter')
                            ->label('Flushing Scope')
                            ->badge()
                            ->color('gray'),

                        IconEntry::make('allow_flushing_override')->label('Allow Overrides')->boolean(),
                        IconEntry::make('auto_post_output')->label('Auto-Post Output')->boolean(),
                        IconEntry::make('auto_post_consumption')->label('Auto-Post Cons.')->boolean(),
                        IconEntry::make('post_capacity')->label('Post Capacity')->boolean(),
                        IconEntry::make('post_time')->label('Post Time')->boolean(),
                        IconEntry::make('post_quantity')->label('Post Qty')->boolean(),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Posting Controls')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('numberSeries.code')->label('No. Series'),
                            TextEntry::make('postingNumberSeries.code')->label('Posting Series')->placeholder('Standard'),
                            TextEntry::make('source_code')->label('Source'),
                            IconEntry::make('consolidate_lines')->label('Consolidate Entries')->boolean(),
                            IconEntry::make('test_report_before_posting')->label('Force Test Report')->boolean(),
                        ]),

                    Section::make('Financials & WIP')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('defaultWipAccount.account_number')
                                ->label('WIP Account')
                                ->formatStateUsing(fn ($state, $record) => $state ? "{$state} – {$record->defaultWipAccount?->name}" : 'Not Configured')
                                ->icon('heroicon-m-building-library'),

                            TextEntry::make('overhead_rate_source')
                                ->label('Overhead Logic')
                                ->badge()
                                ->color('gray'),

                            IconEntry::make('absorb_overhead')->label('Overhead Absorption')->boolean(),
                            IconEntry::make('force_wip_account')->label('Strict WIP Account')->boolean(),
                            IconEntry::make('use_production_order_account_setup')->label('Order-Specific Setup')->boolean(),
                        ]),
                ]),

                Section::make('Dimensions & Audit')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('mandatory_dimensions')->label('Mandatory Dims')->badge(),
                        TextEntry::make('default_dimensions')->label('Default Dims')->badge(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
