<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_MANAGER_APPROVED = 'manager_approved';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_POSTED = 'posted';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'business_id',
        'request_number',
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'start_part',
        'end_part',
        'requested_quantity',
        'approved_quantity',
        'reason',
        'attachment_path',
        'contact_during_leave',
        'handover_notes',
        'status',
        'payroll_review_required',
        'payroll_impact_status',
        'payroll_reference',
        'submitted_at',
        'manager_approved_by',
        'manager_approved_at',
        'hr_approved_by',
        'hr_approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'requested_quantity' => 'decimal:2',
        'approved_quantity' => 'decimal:2',
        'payroll_review_required' => 'boolean',
        'submitted_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EmployeeLeaveLedgerEntry::class);
    }

    public function isApprovedOrPosted(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_POSTED, self::STATUS_COMPLETED], true);
    }
}
