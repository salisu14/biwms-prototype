<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterAssignments\Schemas;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterPeriod;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkforceRosterAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::makeMainColumn(),
                self::makeSidebarColumn(),
            ])
            ->columns(3);
    }

    private static function makeMainColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeBasicInfoSection(),
                self::makeSchedulingSection(),
                self::makeAdvancedOptionsSection(),
            ])
            ->columnSpan(['lg' => 2]);
    }

    private static function makeSidebarColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeStatusSection(),
                self::makeTimeSummarySection(),
            ])
            ->columnSpan(['lg' => 1]);
    }

    // ==================== BASIC INFORMATION ====================

    private static function makeBasicInfoSection(): Section
    {
        return Section::make('Assignment Details')
            ->description('Define who, when, and where')
            ->icon('heroicon-o-user-plus')
            ->schema([
                Group::make()->schema([
                    self::makePeriodSelect(),
                    self::makeEmployeeSelect(),
                    self::makeWorkDateField(),
                    self::makeShiftSelect(),
                    self::makeRosterRoleSelect(),
                    self::makeAssignmentTypeSelect(),
                ]),

                Grid::make(3)->schema([
                    self::makeDepartmentSelect(),
                    self::makeWorkCenterSelect(),
                    self::makeAttendanceLocationSelect(),
                ]),
            ]);
    }

    private static function makePeriodSelect(): Select
    {
        return Select::make('workforce_roster_period_id')
            ->label('Roster Period')
            ->relationship('period', 'name', fn ($query) => $query->whereIn('status', [
                WorkforceRosterPeriod::STATUS_DRAFT,
                WorkforceRosterPeriod::STATUS_GENERATED,
                WorkforceRosterPeriod::STATUS_ACTIVE,
            ])
            )
            ->searchable()
            ->preload(false)
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, Set $set) {
                if (! $state) {
                    return;
                }

                $period = WorkforceRosterPeriod::find($state);
                if (! $period) {
                    return;
                }

                // Store date boundaries for validation
                $set('_period_start', $period->date_from?->toDateString());
                $set('_period_end', $period->date_to?->toDateString());

                // Auto-fill scope from period if empty
                if ($period->department_id) {
                    $set('department_id', $period->department_id);
                }
                if ($period->work_center_id) {
                    $set('work_center_id', $period->work_center_id);
                }
                if ($period->attendance_location_id) {
                    $set('attendance_location_id', $period->attendance_location_id);
                }
            })
            ->helperText('Select the roster period this assignment belongs to');
    }

    private static function makeEmployeeSelect(): Select
    {
        return Select::make('employee_id')
            ->label('Employee')
            ->relationship(
                'employee',
                'full_name',
                fn ($query) => $query->where('is_active', true)->orderBy('last_name')
            )
            ->searchable(['first_name', 'last_name', 'employee_number'])
            ->preload(false)
            ->required()
            ->getSearchResultsUsing(function (string $search): array {
                return Employee::query()
                    ->where('is_active', true)
                    ->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    })
                    ->limit(50)
                    ->get()
                    ->map(fn (Employee $emp) => [
                        'label' => "{$emp->full_name} ({$emp->employee_number})",
                        'value' => $emp->getKey(),
                    ])
                    ->toArray();
            });
        //            ->option(fn (?Employee $record): string =>
        //            $record ? "{$record->full_name} ({$record->employee_number})" : ''
        //            );
    }

    private static function makeWorkDateField(): DatePicker
    {
        return DatePicker::make('work_date')
            ->label('Work Date')
            ->required()
            ->minDate(fn (Get $get) => $get('_period_start') ?? now())
            ->maxDate(fn (Get $get) => $get('_period_end'))
            ->weekStartsOnSunday(false);
    }

    private static function makeShiftSelect(): Select
    {
        return Select::make('employee_shift_id')
            ->label('Shift')
            ->relationship('shift', 'name')
            ->searchable()
            ->preload(false)
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                if (! $state) {
                    return;
                }

                $shift = EmployeeShift::find($state);
                if (! $shift) {
                    return;
                }

                // Only auto-fill if times are currently empty
                if (! $get('expected_start_at') && $shift->start_time) {
                    $workDate = $get('work_date') ?? now()->toDateString();
                    $set('expected_start_at', "{$workDate} {$shift->start_time}");
                }
                if (! $get('expected_end_at') && $shift->end_time) {
                    $workDate = $get('work_date') ?? now()->toDateString();
                    $set('expected_end_at', "{$workDate} {$shift->end_time}");
                }
                if ($shift->break_minutes !== null) {
                    $set('break_minutes', $shift->break_minutes);
                }
            });
    }

    private static function makeRosterRoleSelect(): Select
    {
        return Select::make('roster_role_id')
            ->label('Roster Role')
            ->relationship('rosterRole', 'name')
            ->searchable()
            ->preload(false)
            ->nullable()
            ->placeholder('Optional role');
    }

    private static function makeAssignmentTypeSelect(): Select
    {
        return Select::make('assignment_type')
            ->label('Assignment Type')
            ->options([
                WorkforceRosterAssignment::TYPE_REGULAR => '📋 Regular Shift',
                WorkforceRosterAssignment::TYPE_ROTATION => '🔄 Rotation',
                WorkforceRosterAssignment::TYPE_MANUAL => '✏️ Manual Assignment',
                WorkforceRosterAssignment::TYPE_REPLACEMENT => '👤 Replacement',
                WorkforceRosterAssignment::TYPE_SWAPPED => '↔️ Swapped Shift',
                WorkforceRosterAssignment::TYPE_CALL_IN => '📞 Call-In',
                WorkforceRosterAssignment::TYPE_OVERTIME => '⏰ Overtime',
                WorkforceRosterAssignment::TYPE_TRAINING => '🎓 Training',
                WorkforceRosterAssignment::TYPE_OFFICIAL_DUTY => '🏢 Official Duty',
            ])
            ->default(WorkforceRosterAssignment::TYPE_REGULAR)
            ->required()
            ->native(false);
    }

    private static function makeDepartmentSelect(): Select
    {
        return Select::make('department_id')
            ->label('Department')
            ->relationship('department', 'name')
            ->searchable()
            ->preload(false)
            ->nullable();
    }

    private static function makeWorkCenterSelect(): Select
    {
        return Select::make('work_center_id')
            ->label('Work Center')
            ->relationship('workCenter', 'name')
            ->searchable()
            ->preload(false)
            ->nullable();
    }

    private static function makeAttendanceLocationSelect(): Select
    {
        return Select::make('attendance_location_id')
            ->label('Attendance Location')
            ->relationship('attendanceLocation', 'name')
            ->searchable()
            ->preload(false)
            ->nullable();
    }

    // ==================== SCHEDULING ====================

    private static function makeSchedulingSection(): Section
    {
        return Section::make('Schedule & Timing')
            ->description('Define working hours and break time')
            ->icon('heroicon-o-clock')
            ->schema([
                Fieldset::make('Working Hours')
                    ->columns(2)
                    ->schema([
                        self::makeExpectedStartAtField(),
                        self::makeExpectedEndAtField(),
                        self::makeBreakMinutesField(),
                        self::makeMayCreateOvertimeToggle(),
                    ]),
            ]);
    }

    private static function makeExpectedStartAtField(): DateTimePicker
    {
        return DateTimePicker::make('expected_start_at')
            ->label('Expected Start')
            ->required()
            ->seconds(false)
            ->minutesStep(5)
            ->reactive()
            ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculateDuration($set, $get));
    }

    private static function makeExpectedEndAtField(): DateTimePicker
    {
        return DateTimePicker::make('expected_end_at')
            ->label('Expected End')
            ->required()
            ->seconds(false)
            ->minutesStep(5)
            ->reactive()
            ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalculateDuration($set, $get));
    }

    private static function recalculateDuration(Set $set, Get $get): void
    {
        $start = $get('expected_start_at');
        $end = $get('expected_end_at');

        if (! $start || ! $end) {
            return;
        }

        try {
            $startCarbon = Carbon::parse($start);
            $endCarbon = Carbon::parse($end);

            if ($endCarbon->gt($startCarbon)) {
                $totalMinutes = $startCarbon->diffInMinutes($endCarbon);
                $breakMinutes = (int) ($get('break_minutes') ?? 0);
                $netMinutes = max(0, $totalMinutes - $breakMinutes);

                $hours = floor($netMinutes / 60);
                $mins = $netMinutes % 60;
                $set('_calculated_duration', sprintf('%d hr %d min (%d total)', $hours, $mins, $netMinutes));
            }
        } catch (\Exception $e) {
            // Invalid date format - ignore silently
        }
    }

    private static function makeBreakMinutesField(): TextInput
    {
        return TextInput::make('break_minutes')
            ->label('Break Minutes')
            ->numeric()
            ->default(0)
            ->minValue(0)
            ->maxValue(480)
            ->suffix('minutes')
            ->live(onBlur: true);
    }

    private static function makeMayCreateOvertimeToggle(): Toggle
    {
        return Toggle::make('may_create_overtime')
            ->label('Allow Overtime Generation')
            ->default(false)
            ->inline(false)
            ->helperText('Auto-generate overtime when actual end exceeds expected');
    }

    // ==================== ADVANCED OPTIONS ====================

    private static function makeAdvancedOptionsSection(): Section
    {
        return Section::make('Advanced Options')
            ->icon('heroicon-o-cog-6-tooth')
            ->collapsed()
            ->schema([
                Grid::make(2)->schema([
                    self::makeOriginalAssignmentSelect(),
                    self::makeForecastOvertimeField(),
                ]),

                Textarea::make('cancellation_reason')
                    ->label('Cancellation Reason')
                    ->rows(2)
                    ->placeholder('Reason for cancellation...')
                    ->visible(fn (Get $get, ?WorkforceRosterAssignment $record) => $record?->status === WorkforceRosterAssignment::STATUS_CANCELLED ||
                        $get('status') === WorkforceRosterAssignment::STATUS_CANCELLED
                    ),

                Textarea::make('notes')
                    ->label('Internal Notes')
                    ->rows(3)
                    ->placeholder('Additional notes...'),
            ]);
    }

    private static function makeOriginalAssignmentSelect(): Select
    {
        return Select::make('original_assignment_id')
            ->label('Original Assignment')
            ->relationship('originalAssignment', fn (WorkforceRosterAssignment $a) => "#{$a->id} - {$a->employee?->full_name}"
            )
            ->searchable()
            ->nullable()
            ->placeholder('For replacements/swaps only')
            ->visible(fn (Get $get) => in_array($get('assignment_type'), [
                WorkforceRosterAssignment::TYPE_REPLACEMENT,
                WorkforceRosterAssignment::TYPE_SWAPPED,
            ]));
    }

    private static function makeForecastOvertimeField(): TextInput
    {
        return TextInput::make('forecast_overtime_minutes')
            ->label('Forecasted Overtime')
            ->numeric()
            ->default(0)
            ->suffix('minutes');
    }

    // ==================== STATUS SIDEBAR ====================

    private static function makeStatusSection(): Section
    {
        return Section::make('Status Control')
            ->icon('heroicon-o-shield-check')
            ->schema([
                TextEntry::make('current_status')
                    ->label('Current Status')
                    ->state(function (?WorkforceRosterAssignment $record): string {
                        if (! $record) {
                            return '📝 Draft';
                        }

                        return match ($record->status) {
                            WorkforceRosterAssignment::STATUS_DRAFT => '📝 Draft',
                            WorkforceRosterAssignment::STATUS_SCHEDULED => '📅 Scheduled',
                            WorkforceRosterAssignment::STATUS_PUBLISHED => '✉️ Published',
                            WorkforceRosterAssignment::STATUS_ACCEPTED => '✅ Accepted',
                            WorkforceRosterAssignment::STATUS_DECLINED => '❌ Declined',
                            WorkforceRosterAssignment::STATUS_COMPLETED => '🏁 Completed',
                            WorkforceRosterAssignment::STATUS_ABSENT => '⚠️ Absent',
                            WorkforceRosterAssignment::STATUS_CANCELLED => '🚫 Cancelled',
                            WorkforceRosterAssignment::STATUS_REPLACED => '🔄 Replaced',
                            default => $record->status,
                        };
                    })
                    ->badge()
                    ->color(fn (?WorkforceRosterAssignment $record) => match ($record?->status) {
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

                Select::make('status')
                    ->label('Change Status')
                    ->options([
                        WorkforceRosterAssignment::STATUS_DRAFT => 'Draft',
                        WorkforceRosterAssignment::STATUS_SCHEDULED => 'Scheduled',
                        WorkforceRosterAssignment::STATUS_PUBLISHED => 'Published',
                        WorkforceRosterAssignment::STATUS_ACCEPTED => 'Accepted',
                        WorkforceRosterAssignment::STATUS_DECLINED => 'Declined',
                        WorkforceRosterAssignment::STATUS_COMPLETED => 'Completed',
                        WorkforceRosterAssignment::STATUS_ABSENT => 'Absent',
                        WorkforceRosterAssignment::STATUS_CANCELLED => 'Cancelled',
                        WorkforceRosterAssignment::STATUS_REPLACED => 'Replaced',
                    ])
                    ->disabled(fn (?WorkforceRosterAssignment $record) => $record === null),

                TextEntry::make('conflict_indicator')
                    ->label('Conflict Status')
                    ->state(fn (?WorkforceRosterAssignment $record): string => $record?->conflict_status ? '⚠️ Conflict Detected' : '✅ No Conflicts'
                    )
                    ->badge()
                    ->color(fn (?WorkforceRosterAssignment $record) => $record?->conflict_status ? 'danger' : 'success'
                    ),
            ]);
    }

    private static function makeTimeSummarySection(): Section
    {
        return Section::make('Time Summary')
            ->icon('heroicon-o-calculator')
            ->schema([
                TextEntry::make('duration_display')
                    ->label('Net Duration')
                    ->state(function (Get $get, ?WorkforceRosterAssignment $record): string {
                        if ($record) {
                            $mins = $record->scheduledMinutes();

                            return floor($mins / 60).'h '.($mins % 60).'m';
                        }

                        return $get('_calculated_duration') ?: '—';
                    })
                    ->size('lg')
                    ->weight('bold'),

                TextEntry::make('minutes_display')
                    ->label('Scheduled Minutes')
                    ->state(fn (?WorkforceRosterAssignment $record): string => $record ? number_format($record->scheduledMinutes()).' min' : '—'
                    ),
            ]);
    }
}
