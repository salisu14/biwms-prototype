<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkforceSchedulingRule extends Model
{
    public const TYPE_MINIMUM_REST_HOURS = 'minimum_rest_hours';

    public const TYPE_MAXIMUM_DAILY_HOURS = 'maximum_daily_hours';

    public const TYPE_MAXIMUM_WEEKLY_HOURS = 'maximum_weekly_hours';

    public const TYPE_MAXIMUM_CONSECUTIVE_DAYS = 'maximum_consecutive_days';

    protected $fillable = [
        'business_id', 'code', 'name', 'rule_type', 'value_decimal', 'value_integer',
        'severity', 'department_id', 'work_center_id', 'employee_shift_id',
        'effective_from', 'effective_to', 'is_active',
    ];

    protected $casts = [
        'value_decimal' => 'decimal:4',
        'value_integer' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];
}
