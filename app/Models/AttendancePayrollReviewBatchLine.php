<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePayrollReviewBatchLine extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'attendance_payroll_review_batch_id', 'employee_id', 'employee_attendance_day_id',
        'attendance_review_item_id', 'attendance_payroll_rule_id', 'line_type',
        'quantity_minutes', 'quantity_days', 'rate', 'suggested_amount', 'approved_amount',
        'currency', 'calculation_basis', 'status', 'reviewed_by', 'reviewed_at',
        'rejection_reason', 'payroll_adjustment_reference', 'metadata',
    ];

    protected $casts = [
        'quantity_minutes' => 'integer',
        'quantity_days' => 'decimal:4',
        'rate' => 'decimal:4',
        'suggested_amount' => 'decimal:4',
        'approved_amount' => 'decimal:4',
        'calculation_basis' => 'array',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AttendancePayrollReviewBatch::class, 'attendance_payroll_review_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendanceDay::class, 'employee_attendance_day_id');
    }

    public function reviewItem(): BelongsTo
    {
        return $this->belongsTo(AttendanceReviewItem::class, 'attendance_review_item_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AttendancePayrollRule::class, 'attendance_payroll_rule_id');
    }
}
