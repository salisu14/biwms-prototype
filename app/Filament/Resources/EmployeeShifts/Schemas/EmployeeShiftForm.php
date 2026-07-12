<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeShifts\Schemas;

use App\Models\EmployeeShift;
use Carbon\Carbon;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EmployeeShiftForm
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
                self::makeIdentitySection(),
                self::makeTimeScheduleSection(),
                self::makeBreakAndGraceSection(),
            ])
            ->columnSpan(['lg' => 2]);
    }

    private static function makeSidebarColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeClassificationSection(),
                self::makeCalculatedSummarySection(),
            ])
            ->columnSpan(['lg' => 1]);
    }

    // ==================== IDENTITY ====================

    private static function makeIdentitySection(): Section
    {
        return Section::make('Shift Identity')
            ->icon('heroicon-o-document-text')
            ->schema([
                Grid::make(2)->schema([
                    self::makeCodeField(),
                    self::makeNameField(),
                ]),
            ]);
    }

    private static function makeCodeField(): TextInput
    {
        return TextInput::make('code')
            ->label('Shift Code')
            ->required()
            ->maxLength(20)
            ->unique(ignoreRecord: true)
            ->placeholder('e.g., DAY-MORN, NIGHT-SHIFT')
            ->helperText('Short code for system reference');
    }

    private static function makeNameField(): TextInput
    {
        return TextInput::make('name')
            ->label('Shift Name')
            ->required()
            ->maxLength(100)
            ->placeholder('e.g., Morning Shift, Night Shift')
            ->columnSpanFull();
    }

    // ==================== TIME SCHEDULE ====================

    private static function makeTimeScheduleSection(): Section
    {
        return Section::make('Time Schedule')
            ->description('Define working hours')
            ->icon('heroicon-o-clock')
            ->schema([
                Grid::make(2)->schema([
                    self::makeStartTimeField(),
                    self::makeEndTimeField(),
                    self::makeCrossesMidnightToggle(),
                ]),

                TextEntry::make('shift_duration')
                    ->label('Calculated Duration')
                    ->state(function ($state, Get $get): string {
                        $start = $get('start_time');
                        $end = $get('end_time');
                        $crossesMidnight = $get('crosses_midnight');

                        if (! $start || ! $end) {
                            return '—';
                        }

                        try {
                            $startTime = Carbon::createFromFormat('H:i:s', $start);
                            $endTime = Carbon::createFromFormat('H:i:s', $end);

                            if ($crossesMidnight || $endTime->lt($startTime)) {
                                $endTime->addDay();
                            }

                            $minutes = $startTime->diffInMinutes($endTime);
                            $hours = floor($minutes / 60);
                            $mins = $minutes % 60;

                            return sprintf('%d hr %d min (%d total)', $hours, $mins, $minutes);
                        } catch (\Exception $e) {
                            return 'Invalid time format';
                        }
                    })
                    ->size('lg')
                    ->weight('bold')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn (Get $get) => $get('start_time') && $get('end_time')),
            ]);
    }

    private static function makeStartTimeField(): TimePicker
    {
        return TimePicker::make('start_time')
            ->label('Start Time')
            ->required()
            ->seconds(false)
            ->live()
            ->format('H:i');
    }

    private static function makeEndTimeField(): TimePicker
    {
        return TimePicker::make('end_time')
            ->label('End Time')
            ->required()
            ->seconds(false)
            ->live()
            ->format('H:i');
    }

    private static function makeCrossesMidnightToggle(): Toggle
    {
        return Toggle::make('crosses_midnight')
            ->label('Crosses Midnight')
            ->default(false)
            ->inline(false)
            ->helperText('Enable for night shifts that end after midnight');
    }

    // ==================== BREAK & GRACE ====================

    private static function makeBreakAndGraceSection(): Section
    {
        return Section::make('Break & Grace Periods')
            ->description('Configure flexibility rules')
            ->icon('heroicon-o-adjustments-horizontal')
            ->schema([
                Grid::make(2)->schema([
                    self::makeBreakMinutesField(),
                    self::makeGraceMinutesField(),
                    self::makeEarlyDepartureGraceField(),
                    self::makeOvertimeThresholdField(),
                ]),
            ]);
    }

    private static function makeBreakMinutesField(): TextInput
    {
        return TextInput::make('break_minutes')
            ->label('Break Minutes')
            ->numeric()
            ->default(60)
            ->minValue(0)
            ->maxValue(480)
            ->suffix('min')
            ->helperText('Standard unpaid break duration');
    }

    private static function makeGraceMinutesField(): TextInput
    {
        return TextInput::make('grace_minutes')
            ->label('Late Arrival Grace')
            ->numeric()
            ->default(15)
            ->minValue(0)
            ->maxValue(120)
            ->suffix('min')
            ->helperText('Minutes allowed before marking late');
    }

    private static function makeEarlyDepartureGraceField(): TextInput
    {
        return TextInput::make('early_departure_grace_minutes')
            ->label('Early Departure Grace')
            ->numeric()
            ->default(15)
            ->minValue(0)
            ->maxValue(120)
            ->suffix('min')
            ->helperText('Minutes allowed before early departure penalty');
    }

    private static function makeOvertimeThresholdField(): TextInput
    {
        return TextInput::make('overtime_threshold_minutes')
            ->label('Overtime Threshold')
            ->numeric()
            ->default(30)
            ->minValue(0)
            ->maxValue(240)
            ->suffix('min')
            ->helperText('Minutes beyond end time before OT applies');
    }

    // ==================== CLASSIFICATION SIDEBAR ====================

    private static function makeClassificationSection(): Section
    {
        return Section::make('Classification')
            ->icon('heroicon-o-tag')
            ->schema([
                Toggle::make('is_weekend')
                    ->label('Weekend Shift')
                    ->default(false)
                    ->inline(false)
                    ->helperText('Mark if typically worked on weekends'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Available for new assignments'),
            ]);
    }

    private static function makeCalculatedSummarySection(): Section
    {
        return Section::make('Net Hours')
            ->icon('heroicon-o-calculator')
            ->schema([
                TextEntry::make('net_working_time')
                    ->label('Net Working Time')
                    ->state(function (Get $get): string {
                        $start = $get('start_time');
                        $end = $get('end_time');
                        $break = (int) ($get('break_minutes') ?? 0);

                        if (! $start || ! $end) {
                            return '—';
                        }

                        try {
                            $startTime = Carbon::createFromFormat('H:i:s', $start);
                            $endTime = Carbon::createFromFormat('H:i:s', $end);

                            $crossesMidnight = $get('crosses_midnight');
                            if ($crossesMidnight || $endTime->lt($startTime)) {
                                $endTime->addDay();
                            }

                            $grossMinutes = $startTime->diffInMinutes($endTime);
                            $netMinutes = max(0, $grossMinutes - $break);
                            $netHours = round($netMinutes / 60, 2);

                            return sprintf('%.2f hours', $netHours);
                        } catch (\Exception $e) {
                            return '—';
                        }
                    })
                    ->size('xl')
                    ->weight('bold')
                    ->color('primary'),

                TextEntry::make('assignment_count_info')
                    ->label('Usage')
                    ->state(function (?EmployeeShift $record): string {
                        if (! $record) {
                            return 'New shift';
                        }

                        $count = $record->scheduleAssignments()->count();

                        return number_format($count).' active assignment(s)';
                    })
                    ->visible(fn (?EmployeeShift $record): bool => $record !== null),
            ]);
    }
}
