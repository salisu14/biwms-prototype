<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceStaffingRequirement extends Model
{
    protected $fillable = [
        'business_id',
        'department_id',
        'work_center_id',
        'attendance_location_id',
        'employee_shift_id',
        'roster_role_id',
        'weekday',
        'effective_from',
        'effective_to',
        'minimum_required',
        'target_required',
        'maximum_allowed',
        'is_active',
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

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function attendanceLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'attendance_location_id');
    }

    public function employeeShift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function rosterRole(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterRole::class, 'roster_role_id');
    }
}
