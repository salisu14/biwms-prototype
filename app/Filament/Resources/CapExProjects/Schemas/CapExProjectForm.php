<?php

namespace App\Filament\Resources\CapExProjects\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CapExProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Project Identification')
                    ->description('Primary details and ownership of the Capital Expenditure project.')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('project_number')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('CPX-2024-001'),

                                Select::make('status')
                                    ->options([
                                        'PENDING_APPROVAL' => 'Pending Approval',
                                        'APPROVED' => 'Approved',
                                        'IN_PROGRESS' => 'In Progress',
                                        'ON_HOLD' => 'On Hold',
                                        'COMPLETED' => 'Completed',
                                        'CANCELLED' => 'Cancelled',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default('PENDING_APPROVAL'),

                                Select::make('project_manager_id')
                                    ->relationship('projectManager', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Textarea::make('description')
                                    ->required()
                                    ->columnSpanFull()
                                    ->rows(3),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Financial Budgeting')
                            ->description('Planned vs. Actual expenditure tracking.')
                            ->icon('heroicon-m-banknotes')
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('budget_amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->default(0),

                                TextInput::make('capitalization_threshold')
                                    ->numeric()
                                    ->prefix('$')
                                    ->helperText('Minimum amount to trigger capitalization.')
                                    ->default(0),

                                Select::make('asset_id')
                                    ->label('Target Asset')
                                    ->relationship('targetAsset', 'description')
                                    ->searchable()
                                    ->placeholder('Link to existing asset if applicable'),
                            ]),

                        Section::make('Project Timeline')
                            ->description('Key dates for planning and execution.')
                            ->icon('heroicon-m-calendar')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        DatePicker::make('planned_start_date')->label('Planned Start'),
                                        DatePicker::make('planned_end_date')->label('Planned End'),
                                        DatePicker::make('actual_start_date')->label('Actual Start'),
                                        DatePicker::make('actual_end_date')->label('Actual End'),
                                    ]),
                            ]),
                    ]),

                Section::make('Capitalization Policy')
                    ->description('Configuration for how costs are moved from WIP to Fixed Assets.')
                    ->icon('heroicon-m-arrow-trending-up')
                    ->collapsible()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Toggle::make('capitalize_labor')->label('Labor'),
                                Toggle::make('capitalize_materials')->label('Materials'),
                                Toggle::make('capitalize_overhead')->label('Overhead'),
                                Toggle::make('capitalize_interest')->label('Interest')->live(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('wip_gl_account_id')
                                    ->label('WIP G/L Account')
                                    ->relationship('wipAccount', 'name')
                                    ->searchable()
                                    ->required(),

                                Select::make('capex_gl_account_id')
                                    ->label('CapEx G/L Account')
                                    ->relationship('capexAccount', 'name')
                                    ->searchable()
                                    ->required(),

                                TextInput::make('interest_capitalization_rate')
                                    ->numeric()
                                    ->suffix('%')
                                    ->visible(fn ($get) => $get('capitalize_interest')),
                            ]),
                    ]),
            ]);
    }
}
