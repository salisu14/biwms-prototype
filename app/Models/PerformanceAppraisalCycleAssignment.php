<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceAppraisalCycleAssignment extends Model
{
    protected $fillable = [
        'performance_appraisal_cycle_id',
        'employee_id',
        'department_id',
        'manager_employee_id',
        'secondary_reviewer_employee_id',
        'appraisal_template_id',
        'rating_scale_id',
        'employment_status_snapshot',
        'position_snapshot',
        'grade_snapshot',
        'department_snapshot',
        'manager_snapshot',
        'eligibility_status',
        'exclusion_reason',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceAppraisalCycle::class, 'performance_appraisal_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PerformanceAppraisalTemplate::class, 'appraisal_template_id');
    }

    public function ratingScale(): BelongsTo
    {
        return $this->belongsTo(PerformanceRatingScale::class, 'rating_scale_id');
    }
}
