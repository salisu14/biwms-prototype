<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkforceRosterHistory extends Model
{
    protected $fillable = [
        'workforce_roster_period_id', 'workforce_roster_assignment_id', 'employee_id',
        'event_type', 'changed_by', 'changed_at', 'reason', 'before_values',
        'after_values', 'employee_notified', 'attendance_recalculated', 'attendance_period_locked',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'before_values' => 'array',
        'after_values' => 'array',
        'employee_notified' => 'boolean',
        'attendance_recalculated' => 'boolean',
        'attendance_period_locked' => 'boolean',
    ];
}
