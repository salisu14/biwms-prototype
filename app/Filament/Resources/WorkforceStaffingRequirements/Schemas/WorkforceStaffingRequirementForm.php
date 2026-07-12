<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceStaffingRequirements\Schemas;

use App\Models\AttendanceLocation;
use App\Models\Department;
use App\Models\EmployeeShift;
use App\Models\Manufacturing\WorkCenter;
use App\Models\WorkforceRosterRole;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class WorkforceStaffingRequirementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization & Location')
                    ->icon('heroicon-o-building-office-2')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('business_id')
                                    ->label('Business')
                                    ->relationship(
                                        name: 'business',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                    )
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('department_id', null);
                                        $set('work_center_id', null);
                                        $set('attendance_location_id', null);
                                        $set('employee_shift_id', null);
                                    }),

                                Select::make('department_id')
                                    ->label('Department')
                                    ->options(
                                        fn (Get $get): array => Department::query()
                                            ->when(
                                                filled($get('business_id')),
                                                fn (Builder $query): Builder => $query->where(
                                                    'business_id',
                                                    $get('business_id')
                                                )
                                            )
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->disabled(fn (Get $get): bool => blank($get('business_id'))
                                    )
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('work_center_id', null);
                                    }),

                                Select::make('work_center_id')
                                    ->label('Work Center')
                                    ->options(
                                        fn (Get $get): array => WorkCenter::query()
                                            ->when(
                                                filled($get('business_id')),
                                                fn (Builder $query): Builder => $query->where(
                                                    'business_id',
                                                    $get('business_id')
                                                )
                                            )
                                            ->when(
                                                filled($get('department_id')),
                                                fn (Builder $query): Builder => $query->where(
                                                    'department_id',
                                                    $get('department_id')
                                                )
                                            )
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => blank($get('business_id'))
                                    ),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('attendance_location_id')
                                    ->label('Attendance Location')
                                    ->options(
                                        fn (Get $get): array => AttendanceLocation::query()
                                            ->when(
                                                filled($get('business_id')),
                                                fn (Builder $query): Builder => $query->where(
                                                    'business_id',
                                                    $get('business_id')
                                                )
                                            )
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => blank($get('business_id'))
                                    ),

                                Select::make('employee_shift_id')
                                    ->label('Employee Shift')
                                    ->options(
                                        fn (Get $get): array => EmployeeShift::query()
                                            ->when(
                                                filled($get('business_id')),
                                                fn (Builder $query): Builder => $query->where(
                                                    'business_id',
                                                    $get('business_id')
                                                )
                                            )
                                            ->where('is_active', true)
                                            ->orderBy('start_time')
                                            ->get()
                                            ->mapWithKeys(
                                                fn (EmployeeShift $shift): array => [
                                                    $shift->getKey() => self::getShiftOptionLabel($shift),
                                                ]
                                            )
                                            ->all()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => blank($get('business_id'))
                                    )
                                    ->helperText(
                                        function (mixed $state): ?string {
                                            if (blank($state)) {
                                                return null;
                                            }

                                            $shift = EmployeeShift::find($state);

                                            if (! $shift) {
                                                return null;
                                            }

                                            return self::getShiftTimeRange($shift);
                                        }
                                    ),
                            ]),
                    ]),

                Section::make('Role & Schedule')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('roster_role_id')
                                    ->label('Roster Role')
                                    ->options(
                                        fn (Get $get): array => WorkforceRosterRole::query()
                                            ->when(
                                                filled($get('business_id')),
                                                fn (Builder $query): Builder => $query->where(
                                                    'business_id',
                                                    $get('business_id')
                                                )
                                            )
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->disabled(fn (Get $get): bool => blank($get('business_id'))
                                    ),

                                Select::make('weekday')
                                    ->label('Weekday')
                                    ->options([
                                        1 => 'Monday',
                                        2 => 'Tuesday',
                                        3 => 'Wednesday',
                                        4 => 'Thursday',
                                        5 => 'Friday',
                                        6 => 'Saturday',
                                        7 => 'Sunday',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->placeholder('Select day of week'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('effective_from')
                                    ->label('Effective From')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->default(now()->toDateString())
                                    ->closeOnDateSelection()
                                    ->live()
                                    ->afterStateUpdated(
                                        function (
                                            Set $set,
                                            Get $get,
                                            mixed $state
                                        ): void {
                                            $effectiveTo = $get('effective_to');

                                            if (
                                                blank($state)
                                                || blank($effectiveTo)
                                            ) {
                                                return;
                                            }

                                            if (
                                                Carbon::parse($state)
                                                    ->greaterThan(
                                                        Carbon::parse($effectiveTo)
                                                    )
                                            ) {
                                                $set('effective_to', null);
                                            }
                                        }
                                    ),

                                DatePicker::make('effective_to')
                                    ->label('Effective To')
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->closeOnDateSelection()
                                    ->minDate(
                                        fn (Get $get): mixed => $get('effective_from')
                                    )
                                    ->afterOrEqual('effective_from')
                                    ->validationMessages([
                                        'after_or_equal' => 'The end date must be on or after the start date.',
                                    ]),
                            ]),
                    ]),

                Section::make('Staffing Levels')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('minimum_required')
                                    ->label('Minimum Required')
                                    ->required()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(1)
                                    ->live()
                                    ->suffix('staff')
                                    ->hint('Floor count')
                                    ->rules([
                                        fn (Get $get): \Closure => function (
                                            string $attribute,
                                            mixed $value,
                                            \Closure $fail
                                        ) use ($get): void {
                                            $target = $get(
                                                'target_required'
                                            );

                                            if (
                                                filled($target)
                                                && (int) $value
                                                > (int) $target
                                            ) {
                                                $fail(
                                                    'Minimum required cannot exceed target required.'
                                                );
                                            }
                                        },
                                    ]),

                                TextInput::make('target_required')
                                    ->label('Target Required')
                                    ->required()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(1)
                                    ->live()
                                    ->suffix('staff')
                                    ->hint('Optimal count')
                                    ->rules([
                                        fn (Get $get): \Closure => function (
                                            string $attribute,
                                            mixed $value,
                                            \Closure $fail
                                        ) use ($get): void {
                                            $minimum = $get(
                                                'minimum_required'
                                            );
                                            $maximum = $get(
                                                'maximum_allowed'
                                            );

                                            if (
                                                filled($minimum)
                                                && (int) $value
                                                < (int) $minimum
                                            ) {
                                                $fail(
                                                    'Target required cannot be less than minimum required.'
                                                );
                                            }

                                            if (
                                                filled($maximum)
                                                && (int) $value
                                                > (int) $maximum
                                            ) {
                                                $fail(
                                                    'Target required cannot exceed maximum allowed.'
                                                );
                                            }
                                        },
                                    ]),

                                TextInput::make('maximum_allowed')
                                    ->label('Maximum Allowed')
                                    ->required()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(1)
                                    ->live()
                                    ->suffix('staff')
                                    ->hint('Ceiling count')
                                    ->rules([
                                        fn (Get $get): \Closure => function (
                                            string $attribute,
                                            mixed $value,
                                            \Closure $fail
                                        ) use ($get): void {
                                            $target = $get(
                                                'target_required'
                                            );

                                            if (
                                                filled($target)
                                                && (int) $value
                                                < (int) $target
                                            ) {
                                                $fail(
                                                    'Maximum allowed cannot be less than target required.'
                                                );
                                            }
                                        },
                                    ]),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->inline(false)
                                    ->default(true)
                                    ->onIcon('heroicon-o-check')
                                    ->offIcon('heroicon-o-x-mark'),

                                TextInput::make('coverage_gap')
                                    ->label('Coverage Gap')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->suffix('staff')
                                    ->placeholder(
                                        function (Get $get): string {
                                            $minimum = (int) (
                                                $get('minimum_required') ?? 0
                                            );

                                            $target = (int) (
                                                $get('target_required') ?? 0
                                            );

                                            return (string) max(
                                                0,
                                                $target - $minimum
                                            );
                                        }
                                    )
                                    ->hint('Target − Minimum'),

                                TextInput::make('flex_capacity')
                                    ->label('Flex Capacity')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->suffix('staff')
                                    ->placeholder(
                                        function (Get $get): string {
                                            $target = (int) (
                                                $get('target_required') ?? 0
                                            );

                                            $maximum = (int) (
                                                $get('maximum_allowed') ?? 0
                                            );

                                            return (string) max(
                                                0,
                                                $maximum - $target
                                            );
                                        }
                                    )
                                    ->hint('Maximum − Target'),
                            ]),
                    ]),
            ]);
    }

    private static function getShiftOptionLabel(
        EmployeeShift $shift
    ): string {
        $timeRange = self::getShiftTimeRange($shift);

        return filled($timeRange)
            ? "{$shift->name} ({$timeRange})"
            : $shift->name;
    }

    private static function getShiftTimeRange(
        EmployeeShift $shift
    ): ?string {
        if (
            blank($shift->start_time)
            || blank($shift->end_time)
        ) {
            return null;
        }

        return Carbon::parse($shift->start_time)->format('g:i A')
            .' – '
            .Carbon::parse($shift->end_time)->format('g:i A');
    }
}
