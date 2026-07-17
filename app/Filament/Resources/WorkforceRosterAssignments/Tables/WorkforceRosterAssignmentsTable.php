<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterAssignments\Tables;

use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterPeriod;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WorkforceRosterAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'shift', 'department']))
            ->defaultSort('work_date', 'asc')
            ->defaultPaginationPageOption(25)
            ->columns(self::getColumns())
            ->filters(self::getFilters())
            ->recordActions(self::getActions())
            ->toolbarActions(self::getBulkActions())
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateDescription('No roster assignments found.')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Create Assignment')
                    ->url(route('filament.admin.resources.workforce-roster-assignments.create'))
                    ->button()
                    ->icon('heroicon-o-plus'),
            ])
            ->groups(['work_date' => 'Work Date', 'status' => 'Status']);
    }

    private static function getColumns(): array
    {
        return [
            TextColumn::make('id')
                ->label('#')
//                ->toggleable(isToggledByDefault: false)
                ->sortable(),

            TextColumn::make('employee.full_name')
                ->label('Employee')
                ->searchable(['first_name', 'last_name', 'employee_number'])
                ->sortable()
                ->description(fn (WorkforceRosterAssignment $r): string => $r->employee?->employee_number ?? ''
                )
                ->weight('medium'),

            TextColumn::make('work_date')
                ->label('Date')
                ->date('M d, Y')
                ->sortable()
                ->formatStateUsing(fn (string $s): string => Carbon::parse($s)->format('M d, Y (l)')
                ),

            TextColumn::make('time_range')
                ->label('Time')
                ->getStateUsing(fn (WorkforceRosterAssignment $r): string => $r->expected_start_at && $r->expected_end_at
                    ? Carbon::parse($r->expected_start_at)->format('g:i A').' – '.
                    Carbon::parse($r->expected_end_at)->format('g:i A')
                    : '—'
                )
                ->sortable(query: fn ($q, $dir) => $q->orderBy('expected_start_at', $dir)),

            TextColumn::make('shift.name')
                ->label('Shift')
                ->badge()
                ->color('gray')
                ->searchable()
                ->toggleable(),

            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (string $s): string => str_replace('_', ' ', ucwords($s)))
                ->color(fn (string $s): string => match ($s) {
                    WorkforceRosterAssignment::STATUS_DRAFT => 'gray',
                    WorkforceRosterAssignment::STATUS_SCHEDULED => 'info',
                    WorkforceRosterAssignment::STATUS_PUBLISHED => 'warning',
                    WorkforceRosterAssignment::STATUS_ACCEPTED,
                    WorkforceRosterAssignment::STATUS_COMPLETED => 'success',
                    WorkforceRosterAssignment::STATUS_DECLINED,
                    WorkforceRosterAssignment::STATUS_CANCELLED,
                    WorkforceRosterAssignment::STATUS_ABSENT => 'danger',
                    WorkforceRosterAssignment::STATUS_REPLACED => 'warning',
                    default => 'gray',
                }),

            TextColumn::make('assignment_type')
                ->label('Type')
                ->badge()
                ->formatStateUsing(fn (string $s): string => match ($s) {
                    WorkforceRosterAssignment::TYPE_REGULAR => 'Regular',
                    WorkforceRosterAssignment::TYPE_ROTATION => 'Rotation',
                    WorkforceRosterAssignment::TYPE_MANUAL => 'Manual',
                    WorkforceRosterAssignment::TYPE_REPLACEMENT => 'Replacement',
                    WorkforceRosterAssignment::TYPE_SWAPPED => 'Swapped',
                    WorkforceRosterAssignment::TYPE_CALL_IN => 'Call-In',
                    WorkforceRosterAssignment::TYPE_OVERTIME => 'Overtime',
                    WorkforceRosterAssignment::TYPE_TRAINING => 'Training',
                    WorkforceRosterAssignment::TYPE_OFFICIAL_DUTY => 'Official Duty',
                    default => $s,
                })
                ->color(fn (string $s): string => match ($s) {
                    WorkforceRosterAssignment::TYPE_REGULAR => 'gray',
                    WorkforceRosterAssignment::TYPE_ROTATION => 'primary',
                    WorkforceRosterAssignment::TYPE_MANUAL => 'info',
                    WorkforceRosterAssignment::TYPE_REPLACEMENT,
                    WorkforceRosterAssignment::TYPE_SWAPPED => 'warning',
                    WorkforceRosterAssignment::TYPE_CALL_IN => 'success',
                    WorkforceRosterAssignment::TYPE_OVERTIME => 'danger',
                    WorkforceRosterAssignment::TYPE_TRAINING => 'purple',
                    WorkforceRosterAssignment::TYPE_OFFICIAL_DUTY => 'cyan',
                    default => 'gray',
                }),

            TextColumn::make('department.name')
                ->label('Dept.')
                ->searchable()
                ->toggleable()
                ->placeholder('—'),

            TextColumn::make('duration_hours')
                ->label('Hours')
                ->getStateUsing(fn (WorkforceRosterAssignment $r): string => $r->scheduledMinutes() > 0 ? number_format($r->scheduledMinutes() / 60, 1).'h' : '—'
                )
                ->alignment('right')
                ->toggleable(),

            IconColumn::make('conflict_status')
                ->label('⚠')
                ->boolean()
                ->trueIcon('heroicon-o-exclamation-triangle')
                ->trueColor('danger'),
            //                ->toggleable(isToggledByDefault: false),
        ];
    }

    /**
     * ✅ FIXED: All filter query closures use untyped $state parameter
     * because Filament passes array for form filters and bool for toggles.
     */
    private static function getFilters(): array
    {
        return [
            // ─── Simple Select Filters (no relationship issues) ───

            SelectFilter::make('status')
                ->label('Status')
                ->multiple()
                ->options([
                    WorkforceRosterAssignment::STATUS_DRAFT => 'Draft',
                    WorkforceRosterAssignment::STATUS_SCHEDULED => 'Scheduled',
                    WorkforceRosterAssignment::STATUS_PUBLISHED => 'Published',
                    WorkforceRosterAssignment::STATUS_ACCEPTED => 'Accepted',
                    WorkforceRosterAssignment::STATUS_COMPLETED => 'Completed',
                    WorkforceRosterAssignment::STATUS_CANCELLED => 'Cancelled',
                    WorkforceRosterAssignment::STATUS_ABSENT => 'Absent',
                ]),

            SelectFilter::make('assignment_type')
                ->label('Type')
                ->multiple()
                ->options([
                    WorkforceRosterAssignment::TYPE_REGULAR => 'Regular',
                    WorkforceRosterAssignment::TYPE_ROTATION => 'Rotation',
                    WorkforceRosterAssignment::TYPE_OVERTIME => 'Overtime',
                    WorkforceRosterAssignment::TYPE_REPLACEMENT => 'Replacement',
                    WorkforceRosterAssignment::TYPE_CALL_IN => 'Call-In',
                ]),

            // ─── Relationship Filters (safe pattern) ───

            SelectFilter::make('workforce_roster_period_id')
                ->label('Period')
                ->options(function (): array {
                    return WorkforceRosterPeriod::query()
                        ->whereIn('status', [
                            WorkforceRosterPeriod::STATUS_DRAFT,
                            WorkforceRosterPeriod::STATUS_GENERATED,
                            WorkforceRosterPeriod::STATUS_ACTIVE,
                        ])
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all();
                })
                ->searchable()
                ->preload(false),

            SelectFilter::make('employee_id')
                ->label('Employee')
                ->relationship('employee', 'full_name', fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->orderBy('first_name')
                    ->orderBy('last_name'))
                ->searchable(['first_name', 'last_name', 'employee_number'])
                ->preload(false),

            // ─── Form-Based Filters (receive array $data) ───

            Filter::make('date_range')
                ->label('Date Range')
                ->form([
                    DatePicker::make('date_from')
                        ->label('From'),
                    DatePicker::make('date_to')
                        ->label('To'),
                ])
                ->query(function ($query, array $data): mixed {
                    if (! $query instanceof Builder) {
                        return $query;
                    }

                    $from = $data['date_from'] ?? null;
                    $to = $data['date_to'] ?? null;

                    return $query
                        ->when($from, fn ($q) => $q->whereDate('work_date', '>=', $from))
                        ->when($to, fn ($q) => $q->whereDate('work_date', '<=', $to));
                }),

            // ─── Toggle Filters (receive array with 'isActive' key) ───
            // ✅ CRITICAL FIX: Don't type-hint as bool - Filament passes array!

            Filter::make('active_coverage')
                ->label('Active Only')
                ->toggle()
                ->default(true)
                ->query(function ($query, $state): mixed {
                    // ✅ FIXED: Accept any type, extract bool safely
                    if (! $query instanceof Builder) {
                        return $query;
                    }

                    // Handle both array format and direct bool
                    $isActive = is_array($state) ? ($state['isActive'] ?? true) : (bool) $state;

                    if (! $isActive) {
                        return $query;
                    }

                    return $query->whereNotIn('status', [
                        WorkforceRosterAssignment::STATUS_CANCELLED,
                        WorkforceRosterAssignment::STATUS_REPLACED,
                        WorkforceRosterAssignment::STATUS_DECLINED,
                    ]);
                }),

            Filter::make('has_conflicts')
                ->label('Has Conflicts')
                ->toggle()
                ->query(function ($query, $state): mixed {
                    if (! $query instanceof Builder) {
                        return $query;
                    }

                    // ✅ FIXED: Same safe extraction pattern
                    $isEnabled = is_array($state) ? ($state['isActive'] ?? false) : (bool) $state;

                    if (! $isEnabled) {
                        return $query;
                    }

                    return $query->whereNotNull('conflict_status');
                }),
        ];
    }

    private static function getActions(): array
    {
        return [
            ViewAction::make(),

            EditAction::make()
                ->visible(fn (WorkforceRosterAssignment $r): bool => $r->status === WorkforceRosterAssignment::STATUS_DRAFT
                ),

            ActionGroup::make([
                Action::make('accept')
                    ->label('Accept')
                    ->icon('heroicon-o-hand-raised')
                    ->requiresConfirmation()
                    ->color('success')
                    ->visible(fn (WorkforceRosterAssignment $r): bool => in_array($r->status, [
                        WorkforceRosterAssignment::STATUS_PUBLISHED,
                        WorkforceRosterAssignment::STATUS_SCHEDULED,
                    ]))
                    ->action(fn (WorkforceRosterAssignment $r) => $r->update(['status' => WorkforceRosterAssignment::STATUS_ACCEPTED])
                    ),

                Action::make('complete')
                    ->label('Mark Complete')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->color('success')
                    ->visible(fn (WorkforceRosterAssignment $r): bool => $r->status === WorkforceRosterAssignment::STATUS_ACCEPTED
                    )
                    ->action(fn (WorkforceRosterAssignment $r) => $r->update(['status' => WorkforceRosterAssignment::STATUS_COMPLETED])
                    ),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (array $data, WorkforceRosterAssignment $r) {
                        $r->update([
                            'status' => WorkforceRosterAssignment::STATUS_CANCELLED,
                            'cancelled_by' => auth()->id(),
                            'cancelled_at' => now(),
                            'cancellation_reason' => $data['reason'],
                        ]);
                    })
                    ->visible(fn (WorkforceRosterAssignment $r): bool => ! in_array($r->status, [
                        WorkforceRosterAssignment::STATUS_CANCELLED,
                        WorkforceRosterAssignment::STATUS_COMPLETED,
                    ])),
            ])->dropdown(),
        ];
    }

    private static function getBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),

                Action::make('bulk_publish')
                    ->label('Publish Selected')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(fn (Collection $records) => $records->each(fn (WorkforceRosterAssignment $r) => $r->update([
                        'status' => WorkforceRosterAssignment::STATUS_PUBLISHED,
                        'published_at' => now(),
                    ])
                    )
                    ),
            ]),
        ];
    }
}
