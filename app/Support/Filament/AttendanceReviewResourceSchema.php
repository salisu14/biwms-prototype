<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceDevice;
use App\Models\AttendanceLedgerEntry;
use App\Models\AttendanceLocation;
use App\Models\AttendancePayrollReviewBatch;
use App\Models\AttendancePayrollReviewBatchLine;
use App\Models\AttendancePayrollRule;
use App\Models\AttendanceReviewItem;
use App\Models\AttendanceReviewPeriod;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\EmployeeIdCard;
use App\Models\EmployeeShift;
use App\Models\OvertimeApproval;
use App\Models\PayCode;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkforceRosterAssignment;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceReviewResourceSchema
{
    /**
     * @var array<string, array<int|string, string>>
     */
    private static array $optionCache = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function form(Schema $schema, string $modelClass): Schema
    {
        $config = self::config($modelClass);

        return $schema->components(array_map(
            fn (array $section): Section => Section::make($section['label'])
                ->icon($section['icon'] ?? null)
                ->columns($section['columns'] ?? ['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema(array_map(
                    fn (string $field): object => self::formField($field, $modelClass),
                    $section['fields'],
                )),
            $config['sections'],
        ));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function infolist(Schema $schema, string $modelClass): Schema
    {
        $config = self::config($modelClass);

        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->schema(array_map(
                    fn (array $section): Section => Section::make($section['label'])
                        ->icon($section['icon'] ?? null)
                        ->columns($section['columns'] ?? ['default' => 1, 'md' => 2])
                        ->schema(array_map(
                            fn (string $field): object => self::infolistEntry($field),
                            $section['fields'],
                        )),
                    $config['sections'],
                )),
        ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function table(Table $table, string $modelClass): Table
    {
        $config = self::config($modelClass);

        return $table
            ->columns(array_map(
                fn (string $field): object => self::tableColumn($field),
                $config['table'],
            ))
            ->filters(self::filters($config), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions(self::recordActions($modelClass))
            ->toolbarActions([])
            ->defaultSort($config['defaultSort'] ?? 'updated_at', $config['defaultSortDirection'] ?? 'desc')
            ->emptyStateHeading('No '.Str::headline(class_basename($modelClass)).' records')
            ->emptyStateDescription('Attendance review records will appear here as periods and exceptions are generated.');
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>
     */
    private static function config(string $modelClass): array
    {
        return match ($modelClass) {
            AttendanceReviewPeriod::class => [
                'sections' => [
                    ['label' => 'Review Period', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['business_id', 'code', 'name', 'date_from', 'date_to', 'status', 'notes']],
                    ['label' => 'Submission and Approval', 'icon' => 'heroicon-o-check-badge', 'fields' => ['opened_at', 'submitted_at', 'approved_at', 'locked_at', 'reopened_at', 'reopen_reason']],
                ],
                'table' => ['code', 'name', 'business_id', 'date_from', 'date_to', 'status', 'submitted_at', 'approved_at', 'locked_at'],
                'filters' => ['business_id', 'status'],
                'defaultSort' => 'date_from',
            ],
            AttendanceReviewItem::class => [
                'sections' => [
                    ['label' => 'Exception Context', 'icon' => 'heroicon-o-exclamation-triangle', 'fields' => ['attendance_review_period_id', 'employee_attendance_day_id', 'employee_id', 'attendance_date', 'issue_type', 'severity', 'review_status']],
                    ['label' => 'Original and Resolution Values', 'icon' => 'heroicon-o-code-bracket-square', 'fields' => ['original_values', 'resolved_values', 'source_hash']],
                    ['label' => 'Review Decision', 'icon' => 'heroicon-o-check-circle', 'fields' => ['resolution_type', 'resolution_notes', 'reviewed_by', 'reviewed_at', 'resolved_by', 'resolved_at']],
                ],
                'table' => ['employee_id', 'attendance_date', 'issue_type', 'severity', 'review_status', 'resolution_type', 'reviewed_by', 'resolved_at'],
                'filters' => ['attendance_review_period_id', 'employee_id', 'issue_type', 'severity', 'review_status', 'resolution_type'],
                'defaultSort' => 'attendance_date',
            ],
            AttendancePayrollReviewBatch::class => [
                'sections' => [
                    ['label' => 'Payroll Review Batch', 'icon' => 'heroicon-o-banknotes', 'fields' => ['business_id', 'attendance_review_period_id', 'payroll_period_id', 'batch_number', 'status', 'notes']],
                    ['label' => 'Totals', 'icon' => 'heroicon-o-calculator', 'fields' => ['total_overtime_minutes', 'total_unpaid_minutes', 'total_suggested_amount', 'total_approved_amount']],
                    ['label' => 'Workflow', 'icon' => 'heroicon-o-check-badge', 'fields' => ['generated_at', 'submitted_at', 'approved_at', 'rejected_at', 'rejection_reason', 'posted_at', 'reversed_at', 'reversal_reason']],
                ],
                'table' => ['batch_number', 'attendance_review_period_id', 'payroll_period_id', 'status', 'total_overtime_minutes', 'total_unpaid_minutes', 'total_suggested_amount', 'total_approved_amount', 'posted_at'],
                'filters' => ['business_id', 'attendance_review_period_id', 'payroll_period_id', 'status'],
            ],
            AttendancePayrollReviewBatchLine::class => [
                'sections' => [
                    ['label' => 'Batch Line Context', 'icon' => 'heroicon-o-clipboard-document-list', 'fields' => ['attendance_payroll_review_batch_id', 'employee_id', 'employee_attendance_day_id', 'attendance_review_item_id', 'attendance_payroll_rule_id', 'line_type', 'status']],
                    ['label' => 'Calculation', 'icon' => 'heroicon-o-calculator', 'fields' => ['quantity_minutes', 'quantity_days', 'rate', 'suggested_amount', 'approved_amount', 'currency', 'calculation_basis']],
                    ['label' => 'Review and Payroll', 'icon' => 'heroicon-o-check-circle', 'fields' => ['reviewed_by', 'reviewed_at', 'rejection_reason', 'payroll_adjustment_reference', 'metadata']],
                ],
                'table' => ['employee_id', 'line_type', 'quantity_minutes', 'quantity_days', 'suggested_amount', 'approved_amount', 'status', 'reviewed_at', 'payroll_adjustment_reference'],
                'filters' => ['attendance_payroll_review_batch_id', 'employee_id', 'line_type', 'status', 'attendance_payroll_rule_id'],
            ],
            AttendancePayrollRule::class => [
                'sections' => [
                    ['label' => 'Payroll Rule', 'icon' => 'heroicon-o-scale', 'fields' => ['business_id', 'code', 'name', 'impact_type', 'attendance_issue_type', 'calculation_method', 'rate', 'minimum_minutes', 'maximum_minutes', 'rounding_rule', 'is_active']],
                    ['label' => 'Payroll Components and Effective Period', 'icon' => 'heroicon-o-calendar', 'fields' => ['earning_component_id', 'deduction_component_id', 'effective_from', 'effective_to']],
                ],
                'table' => ['code', 'name', 'business_id', 'impact_type', 'attendance_issue_type', 'calculation_method', 'rate', 'is_active', 'effective_from', 'effective_to'],
                'filters' => ['business_id', 'impact_type', 'attendance_issue_type', 'calculation_method', 'is_active'],
            ],
            EmployeeAttendanceDay::class => [
                'sections' => [
                    ['label' => 'Attendance Day', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['employee_id', 'attendance_date', 'status', 'employee_shift_id', 'attendance_ledger_entry_id', 'workforce_roster_assignment_id']],
                    ['label' => 'Schedule and Time Summary', 'icon' => 'heroicon-o-clock', 'fields' => ['scheduled_start_at', 'scheduled_end_at', 'first_clock_in_at', 'last_clock_out_at', 'worked_minutes', 'late_minutes', 'early_departure_minutes', 'overtime_minutes', 'break_minutes']],
                    ['label' => 'Review and Payroll Flags', 'icon' => 'heroicon-o-exclamation-triangle', 'fields' => ['is_holiday', 'is_weekend', 'on_leave', 'missing_clock_out', 'payroll_review_required', 'payroll_impact_status', 'locked_by_review_period_id', 'locked_at', 'locked_snapshot_hash', 'calculation_notes', 'calculated_at']],
                    ['label' => 'Schedule Trace', 'icon' => 'heroicon-o-map', 'fields' => ['schedule_source', 'schedule_version']],
                ],
                'table' => ['employee_id', 'attendance_date', 'status', 'first_clock_in_at', 'last_clock_out_at', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'payroll_review_required'],
                'filters' => ['employee_id', 'status', 'is_holiday', 'is_weekend', 'on_leave', 'missing_clock_out', 'payroll_review_required'],
                'defaultSort' => 'attendance_date',
            ],
            EmployeeAttendanceEvent::class => [
                'sections' => [
                    ['label' => 'Raw Attendance Event', 'icon' => 'heroicon-o-finger-print', 'fields' => ['employee_id', 'employee_id_card_id', 'event_type', 'occurred_at', 'attendance_date', 'source', 'attendance_device_id', 'attendance_location_id']],
                    ['label' => 'Validation and Trace', 'icon' => 'heroicon-o-shield-check', 'fields' => ['correction_request_id', 'card_token_hash', 'verification_result', 'ip_address', 'user_agent', 'created_by', 'metadata']],
                ],
                'table' => ['employee_id', 'occurred_at', 'attendance_date', 'event_type', 'source', 'verification_result', 'attendance_device_id', 'attendance_location_id'],
                'filters' => ['employee_id', 'event_type', 'source', 'verification_result', 'attendance_device_id', 'attendance_location_id'],
                'defaultSort' => 'occurred_at',
            ],
            AttendanceCorrectionRequest::class => [
                'sections' => [
                    ['label' => 'Correction Request', 'icon' => 'heroicon-o-pencil-square', 'fields' => ['employee_id', 'attendance_day_id', 'attendance_date', 'requested_clock_in_at', 'requested_clock_out_at', 'reason', 'status']],
                    ['label' => 'Review', 'icon' => 'heroicon-o-check-badge', 'fields' => ['requested_by', 'requested_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason']],
                ],
                'table' => ['employee_id', 'attendance_date', 'requested_clock_in_at', 'requested_clock_out_at', 'status', 'requested_by', 'approved_at', 'rejected_at'],
                'filters' => ['employee_id', 'status', 'requested_by', 'approved_by', 'rejected_by'],
            ],
            AttendanceLocation::class => [
                'sections' => [
                    ['label' => 'Attendance Location', 'icon' => 'heroicon-o-map-pin', 'fields' => ['business_id', 'code', 'name', 'timezone', 'latitude', 'longitude', 'allowed_radius_meters', 'is_active']],
                    ['label' => 'Address', 'icon' => 'heroicon-o-document-text', 'fields' => ['address']],
                ],
                'table' => ['code', 'name', 'business_id', 'timezone', 'allowed_radius_meters', 'is_active', 'updated_at'],
                'filters' => ['business_id', 'is_active'],
            ],
            AttendanceDevice::class => [
                'sections' => [
                    ['label' => 'Attendance Device', 'icon' => 'heroicon-o-device-phone-mobile', 'fields' => ['attendance_location_id', 'code', 'name', 'device_type', 'serial_number', 'is_active']],
                    ['label' => 'Connectivity', 'icon' => 'heroicon-o-signal', 'fields' => ['last_seen_at']],
                ],
                'table' => ['code', 'name', 'attendance_location_id', 'device_type', 'serial_number', 'is_active', 'last_seen_at'],
                'filters' => ['attendance_location_id', 'device_type', 'is_active'],
            ],
            OvertimeApproval::class => [
                'sections' => [
                    ['label' => 'Overtime Request', 'icon' => 'heroicon-o-clock', 'fields' => ['employee_id', 'attendance_day_id', 'attendance_date', 'requested_minutes', 'approved_minutes', 'status', 'reason']],
                    ['label' => 'Approval', 'icon' => 'heroicon-o-check-circle', 'fields' => ['requested_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason']],
                ],
                'table' => ['employee_id', 'attendance_date', 'requested_minutes', 'approved_minutes', 'status', 'approved_at'],
                'filters' => ['employee_id', 'status', 'approved_by'],
            ],
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function formField(string $field, string $modelClass): object
    {
        if (self::isLifecycleField($field)) {
            return self::dateTimeField($field)->disabled()->dehydrated(false);
        }

        if (self::isForeignKey($field)) {
            return Select::make($field)
                ->label(self::label($field))
                ->options(fn (): array => self::optionsFor($field))
                ->searchable()
                ->preload()
                ->native(false)
                ->required(self::isRequired($field, $modelClass))
                ->nullable(! self::isRequired($field, $modelClass));
        }

        if (self::hasOptions($field)) {
            return Select::make($field)
                ->label(self::label($field))
                ->options(self::optionsFor($field))
                ->searchable()
                ->native(false)
                ->required(self::isRequired($field, $modelClass))
                ->default(self::defaultFor($field));
        }

        if (self::isBoolean($field)) {
            return Toggle::make($field)->label(self::label($field))->default(false);
        }

        if (self::isDateTime($field)) {
            return self::dateTimeField($field);
        }

        if (self::isDate($field)) {
            $input = DatePicker::make($field)
                ->label(self::label($field))
                ->native(false)
                ->required(self::isRequired($field, $modelClass));

            if (self::dateAfterField($field) !== null) {
                $input = $input->afterOrEqual(self::dateAfterField($field));
            }

            return $input;
        }

        if (in_array($field, ['metadata', 'original_values', 'resolved_values', 'calculation_basis', 'calculation_notes'], true)) {
            return KeyValue::make($field)->label(self::label($field))->columnSpanFull();
        }

        if (self::isLongText($field)) {
            return Textarea::make($field)
                ->label(self::label($field))
                ->rows(4)
                ->columnSpanFull();
        }

        $input = TextInput::make($field)
            ->label(self::label($field))
            ->required(self::isRequired($field, $modelClass))
            ->maxLength(255)
            ->default(self::defaultFor($field));

        if (self::isNumeric($field)) {
            $input = $input
                ->numeric()
                ->minValue(0)
                ->step(Str::contains($field, ['amount', 'rate', 'days']) ? '0.0001' : '1');
        }

        return $input;
    }

    private static function dateTimeField(string $field): DateTimePicker
    {
        return DateTimePicker::make($field)
            ->label(self::label($field))
            ->native(false)
            ->seconds(false);
    }

    private static function infolistEntry(string $field): object
    {
        if (self::isBoolean($field)) {
            return IconEntry::make($field)->label(self::label($field))->boolean();
        }

        $entry = TextEntry::make($field)->label(self::label($field))->placeholder('—');

        if (self::isForeignKey($field)) {
            $entry = $entry->formatStateUsing(fn (mixed $state): ?string => self::optionLabel($field, $state));
        }

        if (self::hasOptions($field)) {
            $entry = $entry
                ->badge()
                ->formatStateUsing(fn (?string $state): string => self::formatOption($field, $state))
                ->color(fn (?string $state): string => self::statusColor($state));
        }

        if (self::isDate($field)) {
            $entry = $entry->date();
        }

        if (self::isDateTime($field)) {
            $entry = $entry->dateTime();
        }

        if (self::isLongText($field) || in_array($field, ['metadata', 'original_values', 'resolved_values', 'calculation_basis', 'calculation_notes'], true)) {
            $entry = $entry->columnSpanFull();
        }

        return $entry;
    }

    private static function tableColumn(string $field): object
    {
        if (self::isBoolean($field)) {
            return IconColumn::make($field)->label(self::label($field))->boolean()->sortable();
        }

        $column = TextColumn::make($field)
            ->label(self::label($field))
            ->placeholder('—')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: Str::endsWith($field, ['_at', '_by']) || in_array($field, ['metadata'], true));

        if (self::isSearchable($field)) {
            $column = $column->searchable();
        }

        if (self::isForeignKey($field)) {
            $column = $column
                ->formatStateUsing(fn (mixed $state): ?string => self::optionLabel($field, $state))
                ->searchable(false);
        }

        if (self::hasOptions($field)) {
            $column = $column
                ->badge()
                ->formatStateUsing(fn (?string $state): string => self::formatOption($field, $state))
                ->color(fn (?string $state): string => self::statusColor($state));
        }

        if (self::isDate($field)) {
            $column = $column->date();
        }

        if (self::isDateTime($field)) {
            $column = $column->dateTime();
        }

        if (self::isNumeric($field)) {
            $column = Str::contains($field, ['amount', 'rate', 'days'])
                ? $column->numeric(decimalPlaces: 2)
                : $column->numeric();
        }

        if (Str::contains($field, ['code', 'number', 'reference'])) {
            $column = $column->copyable()->weight('bold');
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, object>
     */
    private static function filters(array $config): array
    {
        return array_map(function (string $field): object {
            if (self::isBoolean($field)) {
                return TernaryFilter::make($field)->label(self::label($field));
            }

            return SelectFilter::make($field)
                ->label(self::label($field))
                ->options(fn (): array => self::optionsFor($field))
                ->searchable()
                ->preload()
                ->native(false);
        }, $config['filters'] ?? []);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, object>
     */
    private static function recordActions(string $modelClass): array
    {
        if (in_array($modelClass, [EmployeeAttendanceEvent::class], true)) {
            return [ViewAction::make()];
        }

        return [
            ViewAction::make(),
            EditAction::make(),
        ];
    }

    private static function label(string $field): string
    {
        return (string) Str::of($field)
            ->replace([
                'attendance_payroll_review_batch_id',
                'attendance_review_period_id',
                'attendance_review_item_id',
                'employee_attendance_day_id',
                'employee_attendance_event_id',
                'attendance_payroll_rule_id',
                'attendance_location_id',
                'attendance_device_id',
                '_id',
            ], [
                'payroll_review_batch',
                'review_period',
                'review_item',
                'attendance_day',
                'attendance_event',
                'payroll_rule',
                'attendance_location',
                'attendance_device',
                '',
            ])
            ->replace('_', ' ')
            ->headline();
    }

    private static function isRequired(string $field, string $modelClass): bool
    {
        return in_array($field, [
            'code', 'name', 'date_from', 'date_to', 'attendance_review_period_id',
            'employee_id', 'attendance_date', 'issue_type', 'review_status', 'severity',
            'attendance_payroll_review_batch_id', 'batch_number', 'line_type', 'status',
            'attendance_issue_type', 'impact_type', 'calculation_method',
            'occurred_at', 'event_type', 'source',
        ], true);
    }

    private static function isLifecycleField(string $field): bool
    {
        return Str::endsWith($field, '_at')
            && ! in_array($field, ['occurred_at', 'requested_clock_in_at', 'requested_clock_out_at', 'first_clock_in_at', 'last_clock_out_at', 'scheduled_start_at', 'scheduled_end_at'], true);
    }

    private static function isForeignKey(string $field): bool
    {
        return Str::endsWith($field, '_id') || Str::endsWith($field, '_by');
    }

    private static function isBoolean(string $field): bool
    {
        return Str::startsWith($field, ['is_', 'has_', 'requires_']);
    }

    private static function isDate(string $field): bool
    {
        return Str::endsWith($field, '_date') || Str::endsWith($field, '_from') || Str::endsWith($field, '_to') || in_array($field, ['effective_from', 'effective_to'], true);
    }

    private static function isDateTime(string $field): bool
    {
        return Str::endsWith($field, '_at');
    }

    private static function isLongText(string $field): bool
    {
        return Str::contains($field, ['notes', 'reason', 'description', 'address', 'user_agent']);
    }

    private static function isNumeric(string $field): bool
    {
        return Str::contains($field, ['minutes', 'amount', 'rate', 'days', 'meters', 'latitude', 'longitude']);
    }

    private static function isSearchable(string $field): bool
    {
        return in_array($field, ['code', 'name', 'batch_number', 'status', 'issue_type', 'line_type', 'serial_number', 'payroll_adjustment_reference', 'source', 'verification_result'], true);
    }

    private static function dateAfterField(string $field): ?string
    {
        return match ($field) {
            'date_to' => 'date_from',
            'effective_to' => 'effective_from',
            default => null,
        };
    }

    private static function defaultFor(string $field): mixed
    {
        return match ($field) {
            'status' => 'draft',
            'review_status' => AttendanceReviewItem::STATUS_PENDING,
            'severity' => 'warning',
            'is_active' => true,
            default => null,
        };
    }

    private static function hasOptions(string $field): bool
    {
        return array_key_exists($field, self::staticOptions());
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function staticOptions(): array
    {
        return [
            'status' => ['draft' => 'Draft', 'open' => 'Open', 'under_review' => 'Under Review', 'approved' => 'Approved', 'locked' => 'Locked', 'exported' => 'Exported', 'reopened' => 'Reopened', 'pending' => 'Pending', 'reviewed' => 'Reviewed', 'posted' => 'Posted', 'reversed' => 'Reversed', 'rejected' => 'Rejected', 'active' => 'Active', 'inactive' => 'Inactive', 'present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'on_leave' => 'On Leave', 'holiday' => 'Holiday', 'weekend' => 'Weekend', 'missing_clock_out' => 'Missing Clock-Out', 'submitted' => 'Submitted'],
            'review_status' => [AttendanceReviewItem::STATUS_PENDING => 'Pending', AttendanceReviewItem::STATUS_ACKNOWLEDGED => 'Acknowledged', AttendanceReviewItem::STATUS_MANAGER_REVIEWED => 'Manager Reviewed', AttendanceReviewItem::STATUS_HR_REVIEWED => 'HR Reviewed', AttendanceReviewItem::STATUS_RESOLVED => 'Resolved', AttendanceReviewItem::STATUS_WAIVED => 'Waived', AttendanceReviewItem::STATUS_ESCALATED => 'Escalated'],
            'issue_type' => [AttendanceReviewItem::ISSUE_LATE => 'Late Arrival', AttendanceReviewItem::ISSUE_EARLY_DEPARTURE => 'Early Departure', AttendanceReviewItem::ISSUE_ABSENT => 'Absence', AttendanceReviewItem::ISSUE_MISSING_CLOCK_OUT => 'Missing Clock-Out', AttendanceReviewItem::ISSUE_APPROVED_OVERTIME => 'Approved Overtime', AttendanceReviewItem::ISSUE_UNPAID_ABSENCE => 'Unpaid Absence', AttendanceReviewItem::ISSUE_MANUAL_OVERRIDE => 'Manual Override'],
            'severity' => ['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical'],
            'resolution_type' => ['corrected' => 'Corrected', 'waived' => 'Waived', 'payroll_adjustment' => 'Payroll Adjustment', 'no_action' => 'No Action'],
            'line_type' => [AttendanceReviewItem::ISSUE_APPROVED_OVERTIME => 'Approved Overtime', AttendanceReviewItem::ISSUE_UNPAID_ABSENCE => 'Unpaid Absence', AttendanceReviewItem::ISSUE_LATE => 'Lateness', AttendanceReviewItem::ISSUE_EARLY_DEPARTURE => 'Early Departure'],
            'attendance_issue_type' => [AttendanceReviewItem::ISSUE_APPROVED_OVERTIME => 'Approved Overtime', AttendanceReviewItem::ISSUE_UNPAID_ABSENCE => 'Unpaid Absence', AttendanceReviewItem::ISSUE_LATE => 'Lateness', AttendanceReviewItem::ISSUE_EARLY_DEPARTURE => 'Early Departure'],
            'impact_type' => ['earning' => 'Earning', 'deduction' => 'Deduction', 'informational' => 'Informational'],
            'calculation_method' => ['hourly_rate' => 'Hourly Rate', 'hourly_rate_multiplier' => 'Hourly Rate Multiplier', 'fixed_amount' => 'Fixed Amount', 'daily_rate' => 'Daily Rate', 'manual' => 'Manual'],
            'event_type' => ['clock_in' => 'Clock In', 'clock_out' => 'Clock Out', 'correction_clock_in' => 'Correction Clock In', 'correction_clock_out' => 'Correction Clock Out', 'break_start' => 'Break Start', 'break_end' => 'Break End'],
            'source' => ['web' => 'Web', 'manual' => 'Manual', 'qr' => 'QR Code', 'device' => 'Device', 'import' => 'Import'],
            'verification_result' => ['accepted' => 'Accepted', 'duplicate' => 'Duplicate', 'rejected' => 'Rejected', 'invalid' => 'Invalid'],
            'device_type' => ['kiosk' => 'Kiosk', 'biometric' => 'Biometric', 'mobile' => 'Mobile', 'web' => 'Web'],
            'payroll_impact_status' => ['pending_review' => 'Pending Review', 'reviewed' => 'Reviewed', 'exported' => 'Exported'],
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private static function optionsFor(string $field): array
    {
        if (self::hasOptions($field)) {
            return self::staticOptions()[$field];
        }

        if (isset(self::$optionCache[$field])) {
            return self::$optionCache[$field];
        }

        return self::$optionCache[$field] = match ($field) {
            'business_id' => self::pluck(Business::class, 'name'),
            'employee_id' => self::employeeOptions(),
            'opened_by', 'submitted_by', 'approved_by', 'locked_by', 'reopened_by', 'generated_by', 'rejected_by', 'posted_by', 'reversed_by', 'reviewed_by', 'resolved_by', 'recorded_by', 'requested_by' => self::pluck(User::class, 'name'),
            'attendance_review_period_id', 'locked_by_review_period_id' => self::numberedOptions(AttendanceReviewPeriod::class, 'code', 'name'),
            'attendance_payroll_review_batch_id' => self::numberedOptions(AttendancePayrollReviewBatch::class, 'batch_number', 'status'),
            'attendance_review_item_id' => self::numberedOptions(AttendanceReviewItem::class, 'issue_type', 'attendance_date'),
            'attendance_payroll_rule_id' => self::numberedOptions(AttendancePayrollRule::class, 'code', 'name'),
            'employee_attendance_day_id' => self::numberedOptions(EmployeeAttendanceDay::class, 'attendance_date', 'status'),
            'attendance_device_id' => self::numberedOptions(AttendanceDevice::class, 'code', 'name'),
            'attendance_location_id' => self::numberedOptions(AttendanceLocation::class, 'code', 'name'),
            'payroll_period_id' => self::pluck(PayrollPeriod::class, 'name'),
            'employee_shift_id' => self::numberedOptions(EmployeeShift::class, 'code', 'name'),
            'attendance_ledger_entry_id' => self::numberedOptions(AttendanceLedgerEntry::class, 'entry_type', 'attendance_date'),
            'workforce_roster_assignment_id' => self::numberedOptions(WorkforceRosterAssignment::class, 'assignment_date', 'status'),
            'employee_id_card_id' => self::numberedOptions(EmployeeIdCard::class, 'card_number', 'status'),
            'attendance_day_id' => self::numberedOptions(EmployeeAttendanceDay::class, 'attendance_date', 'status'),
            'correction_request_id' => self::numberedOptions(AttendanceCorrectionRequest::class, 'attendance_date', 'status'),
            'earning_component_id', 'deduction_component_id' => self::pluck(PayCode::class, 'code'),
            default => [],
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int|string, string>
     */
    private static function pluck(string $modelClass, string $labelColumn): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;

        if (! DB::getSchemaBuilder()->hasTable($model->getTable()) || ! DB::getSchemaBuilder()->hasColumn($model->getTable(), $labelColumn)) {
            return [];
        }

        return $modelClass::query()
            ->orderBy($labelColumn)
            ->limit(500)
            ->pluck($labelColumn, 'id')
            ->all();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int|string, string>
     */
    private static function numberedOptions(string $modelClass, string $numberColumn, string $labelColumn): array
    {
        /** @var Model $model */
        $model = new $modelClass;

        if (! DB::getSchemaBuilder()->hasTable($model->getTable())) {
            return [];
        }

        return $modelClass::query()
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Model $record): array => [
                $record->getKey() => trim((string) $record->getAttribute($numberColumn).' - '.(string) $record->getAttribute($labelColumn), ' -'),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function employeeOptions(): array
    {
        return Employee::query()
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'employee_number', 'full_name'])
            ->mapWithKeys(fn (Employee $employee): array => [
                $employee->id => trim($employee->employee_number.' - '.$employee->full_name, ' -'),
            ])
            ->all();
    }

    private static function optionLabel(string $field, mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return Arr::get(self::optionsFor($field), $state, '#'.$state);
    }

    private static function formatOption(string $field, ?string $state): string
    {
        if (blank($state)) {
            return 'Not Set';
        }

        return self::staticOptions()[$field][$state] ?? Str::headline($state);
    }

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            'open', 'approved', 'locked', 'resolved', 'posted', 'active', 'reviewed' => 'success',
            'draft', 'pending', 'under_review', 'reopened', 'warning' => 'warning',
            'critical', 'rejected', 'reversed' => 'danger',
            'waived', 'exported', 'inactive' => 'gray',
            default => 'info',
        };
    }
}
