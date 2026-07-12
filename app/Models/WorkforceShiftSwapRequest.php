<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceShiftSwapRequest extends Model
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SUBMITTED = 'submitted';

    public const string STATUS_AWAITING_EMPLOYEE_ACCEPTANCE = 'awaiting_employee_acceptance';

    public const string STATUS_ACCEPTED_BY_EMPLOYEE = 'accepted_by_employee';

    public const string STATUS_MANAGER_REVIEW = 'manager_review';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'requester_employee_id',
        'requester_roster_assignment_id',
        'target_employee_id',
        'target_roster_assignment_id',
        'swap_type',
        'reason',
        'status',
        'accepted_by',
        'accepted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'target_employee_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function requesterAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterAssignment::class, 'requester_roster_assignment_id');
    }
}
