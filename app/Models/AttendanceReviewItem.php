<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceReviewItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_MANAGER_REVIEWED = 'manager_reviewed';

    public const STATUS_HR_REVIEWED = 'hr_reviewed';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_WAIVED = 'waived';

    public const STATUS_ESCALATED = 'escalated';

    public const ISSUE_ABSENT = 'absent';

    public const ISSUE_UNPAID_ABSENCE = 'unpaid_absence';

    public const ISSUE_LATE = 'late';

    public const ISSUE_EARLY_DEPARTURE = 'early_departure';

    public const ISSUE_MISSING_CLOCK_OUT = 'missing_clock_out';

    public const ISSUE_APPROVED_OVERTIME = 'approved_overtime';

    public const ISSUE_UNAPPROVED_OVERTIME = 'unapproved_overtime';

    public const ISSUE_HALF_DAY_LEAVE_VARIANCE = 'half_day_leave_variance';

    public const ISSUE_ATTENDANCE_CORRECTION = 'attendance_correction';

    public const ISSUE_DUPLICATE_EVENT = 'duplicate_event';

    public const ISSUE_INVALID_CARD_EVENT = 'invalid_card_event';

    public const ISSUE_MANUAL_OVERRIDE = 'manual_override';

    protected $fillable = [
        'attendance_review_period_id', 'employee_attendance_day_id', 'employee_id',
        'attendance_date', 'issue_type', 'severity', 'review_status', 'original_values',
        'resolved_values', 'source_hash', 'resolution_type', 'resolution_notes',
        'reviewed_by', 'reviewed_at', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'original_values' => 'array',
        'resolved_values' => 'array',
        'reviewed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AttendanceReviewPeriod::class, 'attendance_review_period_id');
    }

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendanceDay::class, 'employee_attendance_day_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
