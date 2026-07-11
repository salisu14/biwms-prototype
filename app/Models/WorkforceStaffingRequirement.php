<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceStaffingRequirement extends Model
{
    protected $fillable = [
        'business_id', 'department_id', 'work_center_id', 'attendance_location_id',
        'employee_shift_id', 'roster_role_id', 'weekday', 'effective_from', 'effective_to',
        'minimum_required', 'target_required', 'maximum_allowed', 'is_active',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'minimum_required' => 'integer',
        'target_required' => 'integer',
        'maximum_allowed' => 'integer',
        'is_active' => 'boolean',
    ];

    public function rosterRole(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterRole::class, 'roster_role_id');
    }
}
