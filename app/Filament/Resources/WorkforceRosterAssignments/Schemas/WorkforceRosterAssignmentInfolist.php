<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterAssignments\Schemas;

use App\Models\WorkforceRosterAssignment;
use Carbon\Carbon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkforceRosterAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([self::makeGrid()]);
    }

    private static function makeGrid(): Grid
    {
        return Grid::make(3)->schema([
            self::identityColumn(), self::schedulingColumn(), self::timelineColumn(),
        ]);
    }

    private static function identityColumn(): Group
    {
        return Group::make([
            Section::make('Overview')->icon('heroicon-o-user-plus')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('employee.full_name')->label('Employee')->weight('bold')->size('lg')
                        ->url(fn (WorkforceRosterAssignment $r): ?string => route('filament.admin.resources.employees.view', ['record' => $r->employee_id])
                        )
                        ->description(fn (WorkforceRosterAssignment $r): string => trim(($r->employee?->employee_number ?? '').' • '.($r->employee?->department?->name ?? ''), ' •')
                        )->columnSpanFull(),

                    TextEntry::make('period.name')->label('Roster Period')->icon('heroicon-o-calendar-days')
                        ->url(fn (WorkforceRosterAssignment $r): ?string => $r->period ? route('filament.admin.resources.workforce-roster-periods.view', ['record' => $r->workforce_roster_period_id]) : null
                        ),

                    TextEntry::make('work_date')->label('Work Date')->date('F j, Y')
                        ->formatStateUsing(fn (string $s): string => Carbon::parse($s)->format('F j, Y (l)')
                        )->icon('heroicon-o-calendar'),

                    TextEntry::make('status')->label('Status')->badge()
                        ->formatStateUsing(fn (string $s): string => str_replace('_', ' ', ucwords($s)))
                        ->color(fn (string $s): string => match ($s) {
                            WorkforceRosterAssignment::STATUS_DRAFT => 'gray',
                            WorkforceRosterAssignment::STATUS_SCHEDULED => 'info',
                            WorkforceRosterAssignment::STATUS_PUBLISHED => 'warning',
                            WorkforceRosterAssignment::STATUS_ACCEPTED, WorkforceRosterAssignment::STATUS_COMPLETED => 'success',
                            WorkforceRosterAssignment::STATUS_DECLINED, WorkforceRosterAssignment::STATUS_CANCELLED,
                            WorkforceRosterAssignment::STATUS_ABSENT => 'danger',
                            WorkforceRosterAssignment::STATUS_REPLACED => 'warning', default => 'gray',
                        }),

                    TextEntry::make('assignment_type')->label('Type')->badge()
                        ->formatStateUsing(fn (string $s): string => match ($s) {
                            WorkforceRosterAssignment::TYPE_REGULAR => 'Regular',
                            WorkforceRosterAssignment::TYPE_ROTATION => 'Rotation',
                            WorkforceRosterAssignment::TYPE_MANUAL => 'Manual',
                            WorkforceRosterAssignment::TYPE_REPLACEMENT => 'Replacement',
                            WorkforceRosterAssignment::TYPE_SWAPPED => 'Swapped',
                            WorkforceRosterAssignment::TYPE_CALL_IN => 'Call-In',
                            WorkforceRosterAssignment::TYPE_OVERTIME => 'Overtime',
                            WorkforceRosterAssignment::TYPE_TRAINING => 'Training',
                            WorkforceRosterAssignment::TYPE_OFFICIAL_DUTY => 'Official Duty', default => $s,
                        }),

                    TextEntry::make('shift.name')->label('Shift')->badge()->color('gray'),
                ]),
            ]),

            Section::make('Scope')->icon('heroicon-o-map-pin')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('department.name')->label('Department')->icon('heroicon-o-building-office-2'),
                    TextEntry::make('workCenter.name')->label('Work Center')->icon('heroicon-o-cog-6-tooth'),
                    TextEntry::make('attendanceLocation.name')->label('Location')->icon('heroicon-o-map-pin'),
                    TextEntry::make('rosterRole.name')->label('Role')->badge()->color('info'),
                ]),
            ]),

            Section::make('Replacement Chain')->icon('heroicon-o-arrows-right-left')->collapsed()
                ->visible(fn (WorkforceRosterAssignment $r): bool => $r->original_assignment_id !== null || $r->replaced_by_assignment_id !== null
                )->schema([
                    TextEntry::make('originalAssignment.employee.full_name')->label('Replaces')
                        ->icon('heroicon-o-arrow-left')
                        ->formatStateUsing(fn ($s, WorkforceRosterAssignment $r): string => $r->originalAssignment ? "{$r->originalAssignment->employee?->full_name} (#{$r->original_assignment_id})" : 'N/A'
                        )
                        ->visible(fn (WorkforceRosterAssignment $r): bool => $r->original_assignment_id !== null),

                    TextEntry::make('replacementAssignment.employee.full_name')->label('Replaced By')
                        ->icon('heroicon-o-arrow-right')
                        ->formatStateUsing(fn ($s, WorkforceRosterAssignment $r): string => $r->replacementAssignment ? "{$r->replacementAssignment->employee?->full_name} (#{$r->replaced_by_assignment_id})" : 'N/A'
                        )
                        ->visible(fn (WorkforceRosterAssignment $r): bool => $r->replaced_by_assignment_id !== null),
                ]),
        ]);
    }

    private static function schedulingColumn(): Group
    {
        return Group::make([
            Section::make('Timing')->icon('heroicon-o-clock')->schema([
                Grid::make(1)->schema([
                    TextEntry::make('expected_start_at')->label('Start')->dateTime('M d, Y g:i A')
                        ->icon('heroicon-o-play')->size('lg')->weight('bold'),
                    TextEntry::make('expected_end_at')->label('End')->dateTime('M d, Y g:i A')
                        ->icon('heroicon-o-stop')->size('lg')->weight('bold'),
                    TextEntry::make('break_minutes')->label('Break')
                        ->formatStateUsing(fn ($s): string => (int) $s > 0 ? ((int) $s >= 60 ? floor((int) $s / 60).'h '.((int) $s % 60).'m' : $s.' min') : 'None')
                        ->icon('heroicon-o-coffee-cup')->color(fn ($s): string => (int) $s > 0 ? 'warning' : 'gray'),
                    IconEntry::make('may_create_overtime')->label('Overtime Allowed')->boolean()
                        ->trueIcon('heroicon-o-clock')->falseIcon('heroicon-o-x-mark')
                        ->trueColor('warning')->falseColor('gray'),
                ]),
            ]),

            Section::make('Duration')->icon('heroicon-o-calculator')->schema([
                TextEntry::make('net_minutes')->label('Net Minutes')
                    ->getStateUsing(fn (WorkforceRosterAssignment $r): int => $r->scheduledMinutes())
                    ->formatStateUsing(fn (int $s): string => number_format($s).' min')
                    ->size('lg')->weight('bold')->icon('heroicon-o-chart-bar'),
                TextEntry::make('human_duration')->label('Readable Duration')
                    ->getStateUsing(fn (WorkforceRosterAssignment $r): string => ($m = $r->scheduledMinutes()) > 0
                        ? (floor($m / 60) ? floor($m / 60).'h ' : '').($m % 60 ? ($m % 60).'m' : '')
                        : '—'
                    )->icon('heroicon-o-clock')->size('lg'),
                TextEntry::make('forecast_overtime_minutes')->label('Forecasted OT')
                    ->formatStateUsing(fn ($s): string => (int) $s > 0 ? floor((int) $s / 60).'h '.((int) $s % 60).'m' : 'None')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color(fn ($s): string => (int) $s > 0 ? 'warning' : 'success')
                    ->visible(fn (WorkforceRosterAssignment $r): bool => (int) $r->forecast_overtime_minutes > 0 || $r->may_create_overtime),
            ]),

            Section::make('Conflict')->icon('heroicon-o-shield-exclamation')->color('danger')
                ->visible(fn (WorkforceRosterAssignment $r): bool => $r->conflict_status !== null)
                ->schema([
                    IconEntry::make('conflict_status')->label('Conflict')->boolean()
                        ->trueIcon('heroicon-o-exclamation-triangle')->trueColor('danger'),
                    TextEntry::make('conflict_details.message')->label('Details')->markdown()->columnSpanFull()
                        ->visible(fn (WorkforceRosterAssignment $r): bool => ! empty($r->conflict_details['message'])),
                ]),
        ]);
    }

    private static function timelineColumn(): Group
    {
        return Group::make([
            Section::make('Workflow')->icon('heroicon-o-clock')->schema([
                Grid::make(1)->schema([
                    TextEntry::make('created_at')->label('Created')->dateTime('M d, Y g:i A')
                        ->since()->icon('heroicon-o-plus-circle')->size('sm'),
                    TextEntry::make('published_at')->label('Published')->dateTime('M d, Y g:i A')
                        ->since()->placeholder('Not published')->icon('heroicon-o-paper-airplane')
                        ->color('success')->visible(fn (WorkforceRosterAssignment $r): bool => $r->published_at !== null || in_array($r->status, [WorkforceRosterAssignment::STATUS_PUBLISHED, WorkforceRosterAssignment::STATUS_ACCEPTED, WorkforceRosterAssignment::STATUS_COMPLETED])
                        ),
                    TextEntry::make('cancelled_at')->label('Cancelled')->dateTime('M d, Y g:i A')
                        ->since()->placeholder('Not cancelled')->icon('heroicon-o-no-symbol')
                        ->color('danger')->visible(fn (WorkforceRosterAssignment $r): bool => $r->cancelled_at !== null || $r->status === WorkforceRosterAssignment::STATUS_CANCELLED
                        ),
                ]),
            ]),

            Section::make('System')->icon('heroicon-o-information-circle')->collapsed()->schema([
                Grid::make(2)->schema([
                    TextEntry::make('id')->label('ID')->copyable()->icon('heroicon-o-fingerprint')->size('sm')->color('gray'),
                    TextEntry::make('updated_at')->label('Updated')->dateTime('M d, Y g:i A')->since()->icon('heroicon-o-arrow-path')->size('sm'),
                    TextEntry::make('assignedBy.name')->label('Assigned By')->icon('heroicon-o-user')->size('sm')
                        ->visible(fn (WorkforceRosterAssignment $r): bool => $r->assigned_by !== null),
                    TextEntry::make('source_reference_type')->label('Source')
                        ->formatStateUsing(fn ($s, WorkforceRosterAssignment $r): string => $s ? ucfirst($s).' #'.($r->source_reference_id ?? 'N/A') : 'Manual'
                        )->icon('heroicon-o-link')->size('sm')
                        ->visible(fn (WorkforceRosterAssignment $r): bool => $r->source_reference_type !== null),
                ]),
            ]),

            Section::make('Notes')->icon('heroicon-o-chat-bubble-left-right')->collapsed()
                ->visible(fn (WorkforceRosterAssignment $r): bool => ! empty($r->cancellation_reason))
                ->schema([
                    TextEntry::make('cancellation_reason')->label('Cancellation Reason')->markdown()->columnSpanFull(),
                ]),
        ]);
    }
}
