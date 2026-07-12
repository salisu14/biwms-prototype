<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterPeriods\Schemas;

use App\Models\WorkforceRosterPeriod;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkforceRosterPeriodForm
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
                self::makeScopeSection(),
                self::makeNotesSection(),
            ])
            ->columnSpan(['lg' => 2]);
    }

    private static function makeSidebarColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeStatusSection(),
                self::makeDateRangeSummary(),
            ])
            ->columnSpan(['lg' => 1]);
    }

    // ==================== BASIC INFORMATION ====================

    private static function makeBasicInfoSection(): Section
    {
        return Section::make('Basic Information')
            ->description('Define the roster period identity')
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
            ->label('Period Code')
            ->required()
            ->maxLength(20)
            ->unique(ignoreRecord: true)
            ->placeholder('e.g., ROSTER-JAN-2025')
            ->helperText('Unique identifier for this roster period');
    }

    private static function makeNameField(): TextInput
    {
        return TextInput::make('name')
            ->label('Period Name')
            ->required()
            ->maxLength(100)
            ->placeholder('e.g., January 2025 Weekly Roster')
            ->columnSpanFull();
    }

    // ==================== SCOPE & LOCATION ====================

    private static function makeScopeSection(): Section
    {
        return Section::make('Scope & Location')
            ->description('Define which department, work center, and location this roster covers')
            ->icon('heroicon-o-map-pin')
            ->schema([
                Grid::make(2)->schema([
                    self::makeDepartmentSelect(),
                    self::makeWorkCenterSelect(),
                    self::makeAttendanceLocationSelect(),
                ]),

                Fieldset::make('Date Range')
                    ->columns(2)
                    ->schema([
                        self::makeDateFromField(),
                        self::makeDateToField(),
                    ]),
            ]);
    }

    private static function makeDepartmentSelect(): Select
    {
        return Select::make('department_id')
            ->label('Department')
            ->relationship('department', 'name')
            ->searchable()
            ->preload()
            ->placeholder('Select department')
            ->createOptionForm([
                TextInput::make('department_code')
                    ->required()
                    ->maxLength(20),
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
            ])
            ->helperText('Primary department for this roster period');
    }

    private static function makeWorkCenterSelect(): Select
    {
        return Select::make('work_center_id')
            ->label('Work Center')
            ->relationship('workCenter', 'name')
            ->searchable()
            ->preload()
            ->placeholder('Optional: Filter by work center')
            ->nullable();
    }

    private static function makeAttendanceLocationSelect(): Select
    {
        return Select::make('attendance_location_id')
            ->label('Attendance Location')
            ->relationship('attendanceLocation', 'name')
            ->searchable()
            ->preload()
            ->placeholder('Optional: Primary location')
            ->nullable()
            ->helperText('Default location for attendance tracking');
    }

    private static function makeDateFromField(): DatePicker
    {
        return DatePicker::make('date_from')
            ->label('Start Date')
            ->required()
            ->minDate(now()->subMonth())
            ->maxDate(fn (Get $get) => $get('date_to'))
            ->live()
            ->reactive()
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                // Auto-set end date if not set and start date is selected
                if ($state && ! $get('date_to')) {
                    $set('date_to', Carbon::parse($state)->addDays(6)->toDateString());
                }
            });
    }

    private static function makeDateToField(): DatePicker
    {
        return DatePicker::make('date_to')
            ->label('End Date')
            ->required()
            ->minDate(fn (Get $get) => $get('date_from'))
            ->live();
    }

    // ==================== NOTES ====================

    private static function makeNotesSection(): Section
    {
        return Section::make('Additional Information')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->collapsed()
            ->schema([
                Textarea::make('notes')
                    ->label('Notes / Instructions')
                    ->rows(4)
                    ->placeholder('Add any special instructions or notes for this roster period...'),
            ]);
    }

    // ==================== STATUS SIDEBAR ====================

    private static function makeStatusSection(): Section
    {
        return Section::make('Status Control')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Placeholder::make('status_display')
                    ->label('Current Status')
                    ->content(function ($record) {
                        if (! $record) {
                            return '📝 Draft';
                        }

                        return match ($record->status) {
                            WorkforceRosterPeriod::STATUS_DRAFT => '📝 Draft',
                            WorkforceRosterPeriod::STATUS_GENERATED => '⚙️ Generated',
                            WorkforceRosterPeriod::STATUS_UNDER_REVIEW => '👁️ Under Review',
                            WorkforceRosterPeriod::STATUS_PUBLISHED => '✅ Published',
                            WorkforceRosterPeriod::STATUS_ACTIVE => '🟢 Active',
                            WorkforceRosterPeriod::STATUS_CLOSED => '🔒 Closed',
                            WorkforceRosterPeriod::STATUS_CANCELLED => '❌ Cancelled',
                            WorkforceRosterPeriod::STATUS_REOPENED => '🔄 Reopened',
                            default => $record->status,
                        };
                    })
                    ->badge()
                    ->color(fn ($record) => match ($record?->status) {
                        WorkforceRosterPeriod::STATUS_DRAFT => 'gray',
                        WorkforceRosterPeriod::STATUS_GENERATED => 'info',
                        WorkforceRosterPeriod::STATUS_UNDER_REVIEW => 'warning',
                        WorkforceRosterPeriod::STATUS_PUBLISHED,
                        WorkforceRosterPeriod::STATUS_ACTIVE => 'success',
                        WorkforceRosterPeriod::STATUS_CLOSED => 'primary',
                        WorkforceRosterPeriod::STATUS_CANCELLED => 'danger',
                        WorkforceRosterPeriod::STATUS_REOPENED => 'warning',
                        default => 'gray',
                    }),

                Textarea::make('reopen_reason')
                    ->label('Reopen Reason')
                    ->rows(2)
                    ->placeholder('Reason for reopening...')
                    ->visible(fn ($record) => $record?->status === WorkforceRosterPeriod::STATUS_REOPENED)
                    ->required(fn ($record) => $record?->status === WorkforceRosterPeriod::STATUS_REOPENED),
            ]);
    }

    private static function makeDateRangeSummary(): Section
    {
        return Section::make('Period Summary')
            ->icon('heroicon-o-calendar')
            ->schema([
                Placeholder::make('duration_summary')
                    ->label('Duration')
                    ->content(function (Get $get, ?WorkforceRosterPeriod $record) {
                        $from = $record?->date_from ?? $get('date_from');
                        $to = $record?->date_to ?? $get('date_to');

                        if (! $from || ! $to) {
                            return '—';
                        }

                        $startDate = Carbon::parse($from);
                        $endDate = Carbon::parse($to);
                        $days = $startDate->diffInDays($endDate) + 1;
                        $weeks = number_format($days / 7, 1);

                        return "{$days} days ({$weeks} weeks)";
                    }),

                Placeholder::make('assignment_count')
                    ->label('Total Assignments')
                    ->content(function (?WorkforceRosterPeriod $record) {
                        if (! $record) {
                            return '—';
                        }

                        $count = $record->assignments()->count();

                        return number_format($count).' assignment(s)';
                    }),
            ])
            ->visible(fn (?WorkforceRosterPeriod $record) => $record !== null);
    }
}
