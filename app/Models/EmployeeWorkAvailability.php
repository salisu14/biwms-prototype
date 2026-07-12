<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkAvailability extends Model
{
    public const string TYPE_AVAILABLE = 'available';

    public const string TYPE_UNAVAILABLE = 'unavailable';

    public const string TYPE_PREFERRED_SHIFT = 'preferred_shift';

    public const string TYPE_RESTRICTED_SHIFT = 'restricted_shift';

    public const string TYPE_OFFICIAL_DUTY = 'official_duty';

    public const string TYPE_TRAINING = 'training';

    public const string TYPE_TEMPORARY_ASSIGNMENT = 'temporary_assignment';

    public const string TYPE_SUSPENSION = 'suspension';

    public const string TYPE_OTHER = 'other';

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SUBMITTED = 'submitted';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id', 'date_from', 'date_to', 'availability_type', 'employee_shift_id',
        'attendance_location_id', 'work_center_id', 'reason', 'is_confidential', 'status',
        'requested_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'is_confidential' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
