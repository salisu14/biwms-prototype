<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceShiftReplacement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'original_roster_assignment_id', 'original_employee_id', 'replacement_employee_id',
        'replacement_roster_assignment_id', 'replacement_type', 'reason', 'status',
        'proposed_by', 'accepted_by', 'accepted_at', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'may_create_overtime',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'may_create_overtime' => 'boolean',
    ];

    public function originalAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterAssignment::class, 'original_roster_assignment_id');
    }
}
