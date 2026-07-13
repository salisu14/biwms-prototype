<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PerformanceAppraisalCycle extends Model
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_OPEN = 'open';

    public const string STATUS_GOAL_SETTING = 'goal_setting';

    public const string STATUS_SELF_ASSESSMENT = 'self_assessment';

    public const string STATUS_MANAGER_REVIEW = 'manager_review';

    public const string STATUS_MODERATION = 'moderation';

    public const string STATUS_FINALIZATION = 'finalization';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_CLOSED = 'closed';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_REOPENED = 'reopened';

    protected $fillable = [
        'code',
        'name',
        'description',
        'cycle_type',
        'period_start',
        'period_end',
        'goal_setting_start',
        'goal_setting_end',
        'self_assessment_start',
        'self_assessment_end',
        'manager_review_start',
        'manager_review_end',
        'moderation_start',
        'moderation_end',
        'acknowledgement_deadline',
        'status',
        'rating_scale_id',
        'allow_self_assessment',
        'allow_peer_review',
        'allow_secondary_reviewer',
        'require_employee_acknowledgement',
        'require_moderation',
        'lock_completed_reviews',
        'opened_by',
        'opened_at',
        'completed_by',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'goal_setting_start' => 'date',
        'goal_setting_end' => 'date',
        'self_assessment_start' => 'date',
        'self_assessment_end' => 'date',
        'manager_review_start' => 'date',
        'manager_review_end' => 'date',
        'moderation_start' => 'date',
        'moderation_end' => 'date',
        'acknowledgement_deadline' => 'date',
        'allow_self_assessment' => 'boolean',
        'allow_peer_review' => 'boolean',
        'allow_secondary_reviewer' => 'boolean',
        'require_employee_acknowledgement' => 'boolean',
        'require_moderation' => 'boolean',
        'lock_completed_reviews' => 'boolean',
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformanceAppraisalCycle $cycle): void {
            if ($cycle->period_start !== null && $cycle->period_end !== null && Carbon::parse($cycle->period_start)->gt(Carbon::parse($cycle->period_end))) {
                throw new \RuntimeException('Appraisal cycle start date must be on or before end date.');
            }

            foreach ([
                ['goal_setting_start', 'goal_setting_end'],
                ['self_assessment_start', 'self_assessment_end'],
                ['manager_review_start', 'manager_review_end'],
                ['moderation_start', 'moderation_end'],
            ] as [$start, $end]) {
                if ($cycle->{$start} !== null && $cycle->{$end} !== null && Carbon::parse($cycle->{$start})->gt(Carbon::parse($cycle->{$end}))) {
                    throw new \RuntimeException("Appraisal cycle {$start} must be on or before {$end}.");
                }
            }
        });
    }

    public function ratingScale(): BelongsTo
    {
        return $this->belongsTo(PerformanceRatingScale::class, 'rating_scale_id');
    }
    public function assignments(): HasMany
    {
        return $this->hasMany(PerformanceAppraisalCycleAssignment::class, 'performance_appraisal_cycle_id');
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(PerformanceAppraisal::class, 'performance_appraisal_cycle_id');
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CLOSED, self::STATUS_CANCELLED], true);
    }
}
