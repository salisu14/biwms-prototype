<?php

namespace App\Filament\Resources\CapExProjects\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CapExProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('project_number')
                                    ->label('Project #')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'PENDING_APPROVAL' => 'warning',
                                        'APPROVED' => 'success',
                                        'IN_PROGRESS' => 'info',
                                        'COMPLETED' => 'primary',
                                        default => 'gray',
                                    }),
                                TextEntry::make('projectManager.name')
                                    ->label('Project Manager'),
                                TextEntry::make('description')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Financial Status')
                            ->icon('heroicon-m-banknotes')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('budget_amount')->money(),
                                        TextEntry::make('committed_amount')->money(),
                                        TextEntry::make('actual_amount')->money()->color('primary'),
                                        TextEntry::make('capitalized_amount')->money()->color('success'),
                                    ]),
                            ]),

                        Section::make('Timeline')
                            ->icon('heroicon-m-calendar')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('planned_start_date')->date(),
                                        TextEntry::make('planned_end_date')->date(),
                                        TextEntry::make('actual_start_date')->date()->placeholder('Not started'),
                                        TextEntry::make('actual_end_date')->date()->placeholder('Not finished'),
                                    ]),
                            ]),
                    ]),

                Section::make('Capitalization Details')
                    ->icon('heroicon-m-arrow-trending-up')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                IconEntry::make('capitalize_labor')->boolean(),
                                IconEntry::make('capitalize_materials')->boolean(),
                                IconEntry::make('capitalize_overhead')->boolean(),
                                IconEntry::make('capitalize_interest')->boolean(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('capitalization_threshold')->money(),
                                TextEntry::make('interest_capitalization_rate')->suffix('%'),
                                TextEntry::make('capitalized_interest_to_date')->money(),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Audit & Approvals')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('approver.name')->label('Approved By')->placeholder('N/A'),
                                TextEntry::make('approved_at')->dateTime()->placeholder('N/A'),
                                TextEntry::make('creator.name')->label('Created By'),
                            ]),
                    ])
                    ->compact(),
            ]);
    }
}
