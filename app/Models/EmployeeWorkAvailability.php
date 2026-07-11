<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkAvailability extends Model
{
    public const TYPE_AVAILABLE = 'available';

    public const TYPE_UNAVAILABLE = 'unavailable';

    public const TYPE_PREFERRED_SHIFT = 'preferred_shift';

    public const TYPE_RESTRICTED_SHIFT = 'restricted_shift';

    public const TYPE_OFFICIAL_DUTY = 'official_duty';

    public const TYPE_TRAINING = 'training';

    public const TYPE_TEMPORARY_ASSIGNMENT = 'temporary_assignment';

    public const TYPE_SUSPENSION = 'suspension';

    public const TYPE_OTHER = 'other';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

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
