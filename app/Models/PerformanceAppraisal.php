<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PerformanceAppraisal extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SELF_ASSESSMENT_PENDING = 'self_assessment_pending';

    public const STATUS_SELF_ASSESSMENT_SUBMITTED = 'self_assessment_submitted';

    public const STATUS_MANAGER_REVIEW_PENDING = 'manager_review_pending';

    public const STATUS_MANAGER_REVIEW_SUBMITTED = 'manager_review_submitted';

    public const STATUS_MODERATION_PENDING = 'moderation_pending';

    public const STATUS_MODERATED = 'moderated';

    public const STATUS_FINALIZATION_PENDING = 'finalization_pending';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'appraisal_template_version' => 'integer',
        'template_snapshot' => 'array',
        'rating_scale_snapshot' => 'array',
        'calculation_snapshot' => 'array',
        'goal_score' => 'decimal:4',
        'competency_score' => 'decimal:4',
        'other_score' => 'decimal:4',
        'calculated_score' => 'decimal:4',
        'moderated_score' => 'decimal:4',
        'final_score' => 'decimal:4',
        'self_submitted_at' => 'datetime',
        'manager_submitted_at' => 'datetime',
        'moderated_at' => 'datetime',
        'finalized_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(PerformanceAppraisalSection::class)->orderBy('sort_order');
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(PerformanceAppraisalTemplate::class, 'appraisal_template_id');
    }

    public function ratingScale(): BelongsTo
    {
        return $this->belongsTo(PerformanceRatingScale::class, 'rating_scale_id');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(
            PerformanceAppraisalItem::class,
            PerformanceAppraisalSection::class,
            'performance_appraisal_id',
            'performance_appraisal_section_id',
            'id',
            'id',
        );
    }

    public function isFinalizedLike(): bool
    {
        return in_array($this->status, [self::STATUS_FINALIZED, self::STATUS_ACKNOWLEDGED, self::STATUS_CLOSED], true);
    }
}
