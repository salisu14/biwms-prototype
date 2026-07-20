<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceGoalPlan extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_EMPLOYEE_SUBMITTED = 'employee_submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'total_weight_percent' => 'decimal:4',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'revision_requested_at' => 'datetime',
    ];

    public function goals(): HasMany
    {
        return $this->hasMany(PerformanceGoal::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceAppraisalCycle::class, 'performance_appraisal_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function activeGoalWeightTotal(): float
    {
        return (float) $this->goals()
            ->whereNotIn('status', [PerformanceGoal::STATUS_CANCELLED, PerformanceGoal::STATUS_DEFERRED])
            ->sum('weight_percent');
    }
}
