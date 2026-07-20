<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Schemas;

use App\Models\PerformanceAppraisalCycle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PerformanceAppraisalCycleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Basic Information Section
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->label('Cycle Code')
                            ->placeholder('e.g., PA-2024-Q1'),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Cycle Name')
                            ->placeholder('e.g., Q1 2024 Performance Appraisal'),

                        Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull()
                            ->rows(3)
                            ->label('Description'),

                        Select::make('cycle_type')
                            ->options([
                                'annual' => 'Annual',
                                'semi_annual' => 'Semi-Annual',
                                'quarterly' => 'Quarterly',
                                'monthly' => 'Monthly',
                                'project_based' => 'Project Based',
                                'probation' => 'Probation',
                            ])
                            ->required()
                            ->label('Cycle Type'),
                    ])
                    ->columns(2),

                // Period Dates Section
                Section::make('Period Dates')
                    ->schema([
                        DatePicker::make('period_start')
                            ->required()
                            ->label('Period Start Date')
                            ->maxDate(fn (Get $get) => $get('period_end')),

                        DatePicker::make('period_end')
                            ->required()
                            ->label('Period End Date')
                            ->minDate(fn (Get $get) => $get('period_start')),
                    ])
                    ->columns(2),

                // Phase Dates Section
                Section::make('Phase Schedule')
                    ->schema([
                        // Goal Setting Phase
                        TextEntry::make('goal_setting_header')
                            ->state('Goal Setting Phase')
                            ->columnSpanFull(),

                        DatePicker::make('goal_setting_start')
                            ->label('Start Date')
                            ->maxDate(fn (Get $get) => $get('goal_setting_end')),

                        DatePicker::make('goal_setting_end')
                            ->label('End Date')
                            ->minDate(fn (Get $get) => $get('goal_setting_start')),

                        // Self Assessment Phase
                        TextEntry::make('self_assessment_header')
                            ->state('Self Assessment Phase')
                            ->columnSpanFull(),

                        DatePicker::make('self_assessment_start')
                            ->label('Start Date')
                            ->maxDate(fn (Get $get) => $get('self_assessment_end')),

                        DatePicker::make('self_assessment_end')
                            ->label('End Date')
                            ->minDate(fn (Get $get) => $get('self_assessment_start')),

                        // Manager Review Phase
                        TextEntry::make('manager_review_header')
                            ->state('Manager Review Phase')
                            ->columnSpanFull(),

                        DatePicker::make('manager_review_start')
                            ->label('Start Date')
                            ->maxDate(fn (Get $get) => $get('manager_review_end')),

                        DatePicker::make('manager_review_end')
                            ->label('End Date')
                            ->minDate(fn (Get $get) => $get('manager_review_start')),

                        // Moderation Phase
                        TextEntry::make('moderation_header')
                            ->state('Moderation Phase')
                            ->columnSpanFull(),

                        DatePicker::make('moderation_start')
                            ->label('Start Date')
                            ->maxDate(fn (Get $get) => $get('moderation_end')),

                        DatePicker::make('moderation_end')
                            ->label('End Date')
                            ->minDate(fn (Get $get) => $get('moderation_start')),

                        // Acknowledgement Deadline
                        DatePicker::make('acknowledgement_deadline')
                            ->label('Acknowledgement Deadline')
                            ->helperText('Final deadline for employees to acknowledge their appraisals')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(),

                // Configuration Section
                Section::make('Configuration')
                    ->schema([
                        Select::make('rating_scale_id')
                            ->relationship('ratingScale', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Default Rating Scale')
                            ->helperText('This can be overridden at the assignment level'),

                        Toggle::make('allow_self_assessment')
                            ->label('Allow Self Assessment')
                            ->default(true),

                        Toggle::make('allow_peer_review')
                            ->label('Allow Peer Review')
                            ->default(false),

                        Toggle::make('allow_secondary_reviewer')
                            ->label('Allow Secondary Reviewer')
                            ->default(false),

                        Toggle::make('require_employee_acknowledgement')
                            ->label('Require Employee Acknowledgement')
                            ->default(true),

                        Toggle::make('require_moderation')
                            ->label('Require Moderation')
                            ->default(false),

                        Toggle::make('lock_completed_reviews')
                            ->label('Lock Completed Reviews')
                            ->default(true)
                            ->helperText('Prevent modifications after completion'),
                    ])
                    ->columns(2),

                // Status Section (Read-only or controlled)
                Section::make('Status & Audit')
                    ->schema([
                        Select::make('status')
                            ->options([
                                PerformanceAppraisalCycle::STATUS_DRAFT => 'Draft',
                                PerformanceAppraisalCycle::STATUS_OPEN => 'Open',
                                PerformanceAppraisalCycle::STATUS_GOAL_SETTING => 'Goal Setting',
                                PerformanceAppraisalCycle::STATUS_SELF_ASSESSMENT => 'Self Assessment',
                                PerformanceAppraisalCycle::STATUS_MANAGER_REVIEW => 'Manager Review',
                                PerformanceAppraisalCycle::STATUS_MODERATION => 'Moderation',
                                PerformanceAppraisalCycle::STATUS_FINALIZATION => 'Finalization',
                                PerformanceAppraisalCycle::STATUS_COMPLETED => 'Completed',
                                PerformanceAppraisalCycle::STATUS_CLOSED => 'Closed',
                                PerformanceAppraisalCycle::STATUS_CANCELLED => 'Cancelled',
                                PerformanceAppraisalCycle::STATUS_REOPENED => 'Reopened',
                            ])
                            ->required()
                            ->label('Current Status')
                            ->disabled(fn (string $context): bool => $context === 'edit' && ! auth()->user()->can('update_status', PerformanceAppraisalCycle::class)),

                        Textarea::make('reopen_reason')
                            ->label('Reopen Reason')
                            ->visible(fn (Get $get): bool => $get('status') === PerformanceAppraisalCycle::STATUS_REOPENED
                            )
                            ->rows(2),
                    ])
                    ->columns(2),
            ]);
    }
}
