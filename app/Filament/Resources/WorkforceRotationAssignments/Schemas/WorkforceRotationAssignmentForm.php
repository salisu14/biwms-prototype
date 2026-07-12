<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationAssignments\Schemas;

use App\Models\Employee;
use App\Models\WorkforceRotationAssignment;
use App\Models\WorkforceRotationTemplate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkforceRotationAssignmentForm
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
                self::makeCycleConfigurationSection(),
                self::makeScopeSection(),
            ])
            ->columnSpan(['lg' => 2]);
    }

    private static function makeSidebarColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeStatusSection(),
                self::makeValidationInfoSection(),
            ])
            ->columnSpan(['lg' => 1]);
    }

    // ==================== BASIC INFO ====================

    private static function makeBasicInfoSection(): Section
    {
        return Section::make('Rotation Assignment')
            ->description('Assign a rotation template to an employee')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Grid::make(2)->schema([
                    self::makeTemplateSelect(),
                    self::makeEmployeeSelect(),
                ]),
            ]);
    }

    private static function makeTemplateSelect(): Select
    {
        return Select::make('workforce_rotation_template_id')
            ->label('Rotation Template')
            ->relationship('template', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, Set $set) {
                if (! $state) {
                    return;
                }

                $template = WorkforceRotationTemplate::find($state);
                if ($template && $template->cycle_length_days) {
                    $set('_template_cycle_length', $template->cycle_length_days);
                }
            })
            ->helperText('The rotation pattern to apply');
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
            ->preload()
            ->required()
            ->getSearchResultsUsing(function (string $search): array {
                return Employee::query()
                    ->where('is_active', true)
                    ->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    })
                    ->limit(50)
                    ->get()
                    ->map(fn (Employee $e) => [
                        'label' => "{$e->full_name} ({$e->employee_number})",
                        'value' => $e->getKey(),
                    ])
                    ->toArray();
            });
    }

    // ==================== CYCLE CONFIGURATION ====================

    private static function makeCycleConfigurationSection(): Section
    {
        return Section::make('Cycle Configuration')
            ->description('Define when this rotation takes effect')
            ->icon('heroicon-o-calendar-days')
            ->schema([
                Grid::make(2)->schema([
                    self::makeEffectiveFromField(),
                    self::makeEffectiveToField(),
                    self::makeCycleStartDateField(),
                    self::makeStartingSequenceDayField(),
                ]),
            ]);
    }

    private static function makeEffectiveFromField(): DatePicker
    {
        return DatePicker::make('effective_from')
            ->label('Effective From')
            ->required()
            ->minDate(now()->subMonth())
            ->helperText('When this rotation becomes active');
    }

    private static function makeEffectiveToField(): DatePicker
    {
        return DatePicker::make('effective_to')
            ->label('Effective To')
            ->nullable()
            ->minDate(fn (Get $get) => $get('effective_from'))
            ->helperText('Leave blank for indefinite');
    }

    private static function makeCycleStartDateField(): DatePicker
    {
        return DatePicker::make('cycle_start_date')
            ->label('Cycle Start Date')
            ->required()
            ->helperText('Reference date for calculating cycle position')
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                // Auto-set sequence day if not set
                if ($state && ! $get('starting_sequence_day')) {
                    $set('starting_sequence_day', 1);
                }
            });
    }

    private static function makeStartingSequenceDayField(): TextInput
    {
        return TextInput::make('starting_sequence_day')
            ->label('Starting Sequence Day')
            ->numeric()
            ->default(1)
            ->minValue(1)
            ->maxValue(365)
            ->helperText('Day position within the rotation cycle (1-based)');
    }

    // ==================== SCOPE ====================

    private static function makeScopeSection(): Section
    {
        return Section::make('Scope & Location')
            ->icon('heroicon-o-map-pin')
            ->schema([
                Grid::make(2)->schema([
                    self::makeWorkCenterSelect(),
                    self::makeAttendanceLocationSelect(),
                ]),

                Grid::make(2)->schema([
                    self::makeIsPrimaryToggle(),
                    self::makeIsActiveToggle(),
                ]),
            ]);
    }

    private static function makeWorkCenterSelect(): Select
    {
        return Select::make('work_center_id')
            ->label('Work Center')
            ->relationship('workCenter', 'name')
            ->searchable()
            ->preload()
            ->nullable()
            ->placeholder('Optional work center constraint');
    }

    private static function makeAttendanceLocationSelect(): Select
    {
        return Select::make('attendance_location_id')
            ->label('Attendance Location')
            ->relationship('attendanceLocation', 'name')
            ->searchable()
            ->preload()
            ->nullable()
            ->placeholder('Optional location override');
    }

    private static function makeIsPrimaryToggle(): Toggle
    {
        return Toggle::make('is_primary')
            ->label('Primary Rotation')
            ->default(true)
            ->inline(false)
            ->helperText('Only one active primary rotation per employee/date allowed');
    }

    private static function makeIsActiveToggle(): Toggle
    {
        return Toggle::make('is_active')
            ->label('Active')
            ->default(true)
            ->inline(false)
            ->helperText('Whether this rotation assignment is currently active');
    }

    // ==================== STATUS SIDEBAR ====================

    private static function makeStatusSection(): Section
    {
        return Section::make('Assignment Status')
            ->icon('heroicon-o-shield-check')
            ->schema([
                TextEntry::make('status_summary')
                    ->label('Current State')
                    ->state(function (Get $get, ?WorkforceRotationAssignment $record): string {
                        $isActive = $record?->is_active ?? $get('is_active') ?? false;
                        $isPrimary = $record?->is_primary ?? $get('is_primary') ?? false;

                        if ($isActive && $isPrimary) {
                            return '🟢 Active Primary';
                        }
                        if ($isActive && ! $isPrimary) {
                            return '🟡 Active Secondary';
                        }
                        if (! $isActive) {
                            return '⚪ Inactive';
                        }

                        return '—';
                    })
                    ->badge()
                    ->color(function (Get $get, ?WorkforceRotationAssignment $record): string {
                        $isActive = $record?->is_active ?? $get('is_active') ?? false;

                        return $isActive ? 'success' : 'gray';
                    }),

                TextEntry::make('overlap_warning')
                    ->label('Overlap Check')
                    ->state('✅ No conflicts detected')
                    ->badge()
                    ->color('success')
                    ->visible(fn (?WorkforceRotationAssignment $record): bool => $record === null),
            ]);
    }

    private static function makeValidationInfoSection(): Section
    {
        return Section::make('Business Rules')
            ->icon('heroicon-o-exclamation-triangle')
            ->collapsed()
            ->schema([
                TextEntry::make('rule_1')
                    ->hiddenLabel()
                    ->state('• Only one **active primary** rotation per employee/date range')
                    ->markdown()
                    ->size('sm'),

                TextEntry::make('rule_2')
                    ->hiddenLabel()
                    ->state('• Effective To can be left blank for **indefinite** rotations')
                    ->markdown()
                    ->size('sm'),

                TextEntry::make('rule_3')
                    ->hiddenLabel()
                    ->state('• Sequence day determines starting position in the **cycle pattern**')
                    ->markdown()
                    ->size('sm'),
            ]);
    }
}
