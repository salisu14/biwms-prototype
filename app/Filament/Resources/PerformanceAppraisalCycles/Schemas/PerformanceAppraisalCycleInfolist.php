<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Schemas;

use App\Models\PerformanceAppraisalCycle;
use App\Models\PerformanceRatingScale;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class PerformanceAppraisalCycleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')
                    ->label('Cycle Code')
                    ->weight('bold')
                    ->size(TextSize::Large),

                TextEntry::make('name')
                    ->label('Name')
                    ->size(TextSize::Large),

                TextEntry::make('description')
                    ->label('Description')
                    ->columnSpanFull(),

                TextEntry::make('cycle_type')
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->badge()
                    ->label('Type'),

                TextEntry::make('status')
                    ->badge()
                    ->colors([
                        'gray' => PerformanceAppraisalCycle::STATUS_DRAFT,
                        'success' => PerformanceAppraisalCycle::STATUS_OPEN,
                        'info' => fn (string $state) => in_array($state, [
                            PerformanceAppraisalCycle::STATUS_GOAL_SETTING,
                            PerformanceAppraisalCycle::STATUS_SELF_ASSESSMENT,
                            PerformanceAppraisalCycle::STATUS_MANAGER_REVIEW,
                            PerformanceAppraisalCycle::STATUS_MODERATION,
                            PerformanceAppraisalCycle::STATUS_FINALIZATION,
                        ]),
                        'warning' => PerformanceAppraisalCycle::STATUS_REOPENED,
                        'danger' => PerformanceAppraisalCycle::STATUS_CANCELLED,
                        'primary' => PerformanceAppraisalCycle::STATUS_COMPLETED,
                        'secondary' => PerformanceAppraisalCycle::STATUS_CLOSED,
                    ])
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                Section::make('Period Timeline')
                    ->schema([
                        TextEntry::make('period_start')
                            ->date()
                            ->label('Period Start'),

                        TextEntry::make('period_end')
                            ->date()
                            ->label('Period End'),
                    ])
                    ->columns(2),

                Section::make('Phase Schedule')
                    ->schema([
                        Group::make([
                            TextEntry::make('goal_setting_start')
                                ->date()
                                ->label('Start'),

                            TextEntry::make('goal_setting_end')
                                ->date()
                                ->label('End'),
                        ])->label('Goal Setting')->columns(2),

                        Group::make([
                            TextEntry::make('self_assessment_start')
                                ->date()
                                ->label('Start'),

                            TextEntry::make('self_assessment_end')
                                ->date()
                                ->label('End'),
                        ])->label('Self Assessment')->columns(2),

                        Group::make([
                            TextEntry::make('manager_review_start')
                                ->date()
                                ->label('Start'),

                            TextEntry::make('manager_review_end')
                                ->date()
                                ->label('End'),
                        ])->label('Manager Review')->columns(2),

                        Group::make([
                            TextEntry::make('moderation_start')
                                ->date()
                                ->label('Start'),

                            TextEntry::make('moderation_end')
                                ->date()
                                ->label('End'),
                        ])->label('Moderation')->columns(2),

                        TextEntry::make('acknowledgement_deadline')
                            ->date()
                            ->label('Acknowledgement Deadline')
                            ->placeholder('Not set'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Section::make('Configuration')
                    ->schema([
                        TextEntry::make('ratingScale.name')
                            ->label('Default Rating Scale')
                            ->placeholder('Not set'),

                        IconEntry::make('allow_self_assessment')
                            ->boolean()
                            ->label('Allow Self Assessment'),

                        IconEntry::make('allow_peer_review')
                            ->boolean()
                            ->label('Allow Peer Review'),

                        IconEntry::make('allow_secondary_reviewer')
                            ->boolean()
                            ->label('Allow Secondary Reviewer'),

                        IconEntry::make('require_employee_acknowledgement')
                            ->boolean()
                            ->label('Require Acknowledgement'),

                        IconEntry::make('require_moderation')
                            ->boolean()
                            ->label('Require Moderation'),

                        IconEntry::make('lock_completed_reviews')
                            ->boolean()
                            ->label('Lock Completed Reviews'),
                    ])
                    ->columns(3),

                Section::make('Audit Trail')
                    ->schema([
                        TextEntry::make('opened_by')
                            ->label('Opened By')
                            ->placeholder('N/A'),

                        TextEntry::make('opened_at')
                            ->dateTime()
                            ->label('Opened At')
                            ->placeholder('N/A'),

                        TextEntry::make('completed_by')
                            ->label('Completed By')
                            ->placeholder('N/A'),

                        TextEntry::make('completed_at')
                            ->dateTime()
                            ->label('Completed At')
                            ->placeholder('N/A'),

                        TextEntry::make('reopened_by')
                            ->label('Reopened By')
                            ->placeholder('N/A'),

                        TextEntry::make('reopened_at')
                            ->dateTime()
                            ->label('Reopened At')
                            ->placeholder('N/A'),

                        TextEntry::make('reopen_reason')
                            ->label('Reopen Reason')
                            ->placeholder('N/A')
                            ->columnSpanFull(),

                        TextEntry::make('closed_by')
                            ->label('Closed By')
                            ->placeholder('N/A'),

                        TextEntry::make('closed_at')
                            ->dateTime()
                            ->label('Closed At')
                            ->placeholder('N/A'),

                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created At'),

                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->label('Updated At'),
                    ])
                    ->columns(4)
                    ->collapsed(),
            ]);
    }
}
