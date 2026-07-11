<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendanceDay extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_WEEKEND = 'weekend';

    public const STATUS_MISSING_CLOCK_OUT = 'missing_clock_out';

    protected $fillable = [
        'employee_id',
        'employee_shift_id',
        'attendance_ledger_entry_id',
        'locked_by_review_period_id',
        'locked_at',
        'locked_snapshot_hash',
        'attendance_date',
        'scheduled_start_at',
        'scheduled_end_at',
        'first_clock_in_at',
        'last_clock_out_at',
        'break_minutes',
        'worked_minutes',
        'late_minutes',
        'early_departure_minutes',
        'overtime_minutes',
        'status',
        'is_holiday',
        'is_weekend',
        'on_leave',
        'missing_clock_out',
        'payroll_review_required',
        'payroll_impact_status',
        'calculation_notes',
        'calculated_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'locked_at' => 'datetime',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'first_clock_in_at' => 'datetime',
        'last_clock_out_at' => 'datetime',
        'break_minutes' => 'integer',
        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_departure_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'is_holiday' => 'boolean',
        'is_weekend' => 'boolean',
        'on_leave' => 'boolean',
        'missing_clock_out' => 'boolean',
        'payroll_review_required' => 'boolean',
        'calculation_notes' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(AttendanceLedgerEntry::class, 'attendance_ledger_entry_id');
    }

    public function lockedByReviewPeriod(): BelongsTo
    {
        return $this->belongsTo(AttendanceReviewPeriod::class, 'locked_by_review_period_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_by_review_period_id !== null || $this->locked_at !== null;
    }
}
