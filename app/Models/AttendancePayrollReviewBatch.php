<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePayrollReviewBatch extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_POSTED = 'posted';

    public const STATUS_PARTIALLY_POSTED = 'partially_posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'business_id', 'attendance_review_period_id', 'payroll_period_id', 'batch_number',
        'status', 'generated_by', 'generated_at', 'submitted_by', 'submitted_at',
        'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason',
        'posted_by', 'posted_at', 'reversed_by', 'reversed_at', 'reversal_reason',
        'total_overtime_minutes', 'total_unpaid_minutes', 'total_suggested_amount',
        'total_approved_amount', 'notes',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'total_overtime_minutes' => 'integer',
        'total_unpaid_minutes' => 'integer',
        'total_suggested_amount' => 'decimal:4',
        'total_approved_amount' => 'decimal:4',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AttendanceReviewPeriod::class, 'attendance_review_period_id');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AttendancePayrollReviewBatchLine::class);
    }
}
