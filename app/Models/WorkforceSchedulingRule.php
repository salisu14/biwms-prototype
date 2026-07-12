<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceSchedulingRule extends Model
{
    public const string TYPE_MINIMUM_REST_HOURS = 'minimum_rest_hours';

    public const string TYPE_MAXIMUM_DAILY_HOURS = 'maximum_daily_hours';

    public const string TYPE_MAXIMUM_WEEKLY_HOURS = 'maximum_weekly_hours';

    public const string TYPE_MAXIMUM_CONSECUTIVE_DAYS = 'maximum_consecutive_days';

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'rule_type',
        'value_decimal',
        'value_integer',
        'severity',
        'department_id',
        'work_center_id',
        'employee_shift_id',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'value_decimal' => 'decimal:4',
        'value_integer' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function employeeShift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }
}
