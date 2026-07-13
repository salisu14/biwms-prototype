<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Tables;

use App\Models\PerformanceAppraisalCycle;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PerformanceAppraisalCyclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->label('Code')
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(fn($record) => $record->name)
                    ->label('Name'),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => PerformanceAppraisalCycle::STATUS_DRAFT,
                        'success' => PerformanceAppraisalCycle::STATUS_OPEN,
                        'info' => [
                            PerformanceAppraisalCycle::STATUS_GOAL_SETTING,
                            PerformanceAppraisalCycle::STATUS_SELF_ASSESSMENT,
                            PerformanceAppraisalCycle::STATUS_MANAGER_REVIEW,
                            PerformanceAppraisalCycle::STATUS_MODERATION,
                            PerformanceAppraisalCycle::STATUS_FINALIZATION,
                        ],
                        'warning' => PerformanceAppraisalCycle::STATUS_REOPENED,
                        'danger' => PerformanceAppraisalCycle::STATUS_CANCELLED,
                        'primary' => PerformanceAppraisalCycle::STATUS_COMPLETED,
                        'secondary' => PerformanceAppraisalCycle::STATUS_CLOSED,
                    ])
                    ->formatStateUsing(fn(string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->label('Status'),

                TextColumn::make('cycle_type')
                    ->formatStateUsing(fn(string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->badge()
                    ->label('Type'),

                TextColumn::make('period_start')
                    ->date()
                    ->sortable()
                    ->label('Start'),

                TextColumn::make('period_end')
                    ->date()
                    ->sortable()
                    ->label('End'),

                IconColumn::make('allow_self_assessment')
                    ->boolean()
                    ->label('Self Assess.')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('require_moderation')
                    ->boolean()
                    ->label('Moderation')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('assignments_count')
                    ->counts('assignments')
                    ->label('Assignments')
                    ->sortable()
                    ->badge(),

                TextColumn::make('appraisals_count')
                    ->counts('appraisals')
                    ->label('Appraisals')
                    ->sortable()
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Created At'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
//                    ->toggleable(isToggledHiddenBy: true)
                    ->label('Updated At'),
            ])
            ->filters([
                SelectFilter::make('status')
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
                    ->label('Status'),

                SelectFilter::make('cycle_type')
                    ->options([
                        'annual' => 'Annual',
                        'semi_annual' => 'Semi-Annual',
                        'quarterly' => 'Quarterly',
                        'monthly' => 'Monthly',
                        'project_based' => 'Project Based',
                        'probation' => 'Probation',
                    ])
                    ->label('Cycle Type'),

                TernaryFilter::make('allow_self_assessment')
                    ->label('Allow Self Assessment'),

                TernaryFilter::make('require_moderation')
                    ->label('Require Moderation'),

                Filter::make('active_cycles')
                    ->query(fn(Builder $query): Builder => $query->whereNotIn('status', [
                        PerformanceAppraisalCycle::STATUS_CLOSED,
                        PerformanceAppraisalCycle::STATUS_CANCELLED,
                    ]))
                    ->label('Active Cycles Only')
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        if (isset($data['status']) && $data['status'] === PerformanceAppraisalCycle::STATUS_REOPENED && empty($data['reopen_reason'])) {
                            throw new \Exception('Reopen reason is required when reopening a cycle.');
                        }
                        return $data;
                    }),
                Action::make('openCycle')
                    ->label('Open Cycle')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (PerformanceAppraisalCycle $record): void {
                        $record->update([
                            'status' => PerformanceAppraisalCycle::STATUS_OPEN,
                            'opened_by' => auth()->id(),
                            'opened_at' => now(),
                        ]);
                    })
                    ->visible(fn(PerformanceAppraisalCycle $record): bool => $record->status === PerformanceAppraisalCycle::STATUS_DRAFT
                    ),

                Action::make('completeCycle')
                    ->label('Complete Cycle')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (PerformanceAppraisalCycle $record): void {
                        $record->update([
                            'status' => PerformanceAppraisalCycle::STATUS_COMPLETED,
                            'completed_by' => auth()->id(),
                            'completed_at' => now(),
                        ]);
                    })
                    ->visible(fn(PerformanceAppraisalCycle $record): bool => !$record->isLocked() && $record->status !== PerformanceAppraisalCycle::STATUS_DRAFT
                    ),

                Action::make('closeCycle')
                    ->label('Close Cycle')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PerformanceAppraisalCycle $record): void {
                        $record->update([
                            'status' => PerformanceAppraisalCycle::STATUS_CLOSED,
                            'closed_by' => auth()->id(),
                            'closed_at' => now(),
                        ]);
                    })
                    ->visible(fn(PerformanceAppraisalCycle $record): bool => $record->status === PerformanceAppraisalCycle::STATUS_COMPLETED
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No appraisal cycles found')
            ->emptyStateDescription('Create a new performance appraisal cycle to get started.')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Create Appraisal Cycle'),
            ]);
    }
}
