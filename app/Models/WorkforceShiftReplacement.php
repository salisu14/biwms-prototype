<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceShiftReplacement extends Model
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_PROPOSED = 'proposed';

    public const string STATUS_ACCEPTED = 'accepted';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'original_roster_assignment_id',
        'original_employee_id',
        'replacement_employee_id',
        'replacement_roster_assignment_id',
        'replacement_type',
        'reason',
        'status',
        'proposed_by',
        'accepted_by',
        'accepted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'may_create_overtime',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'may_create_overtime' => 'boolean',
    ];

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rejected_by');
    }

    public function originalEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'original_employee_id');
    }

    public function replacementAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterAssignment::class, 'replacement_roster_assignment_id');
    }

    public function replacementEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'replacement_employee_id');
    }

    public function replacementRosterAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterAssignment::class, 'replacement_roster_assignment_id');
    }

    public function originalAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterAssignment::class, 'original_roster_assignment_id');
    }
}
