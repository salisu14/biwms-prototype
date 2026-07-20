<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PerformanceAppraisalTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'description',
        'applicable_department_id',
        'applicable_position_id',
        'applicable_grade_id',
        'applicable_employment_type',
        'rating_scale_id',
        'goal_weight_percent',
        'competency_weight_percent',
        'other_weight_percent',
        'require_self_comment',
        'require_manager_comment',
        'require_final_comment',
        'allow_not_applicable',
        'is_active',
        'effective_from',
        'effective_to',
        'version',
    ];

    protected $casts = [
        'goal_weight_percent' => 'decimal:4',
        'competency_weight_percent' => 'decimal:4',
        'other_weight_percent' => 'decimal:4',

        'require_self_comment' => 'boolean',
        'require_manager_comment' => 'boolean',
        'require_final_comment' => 'boolean',
        'allow_not_applicable' => 'boolean',
        'is_active' => 'boolean',

        'effective_from' => 'date',
        'effective_to' => 'date',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformanceAppraisalTemplate $template): void {
            $totalWeight = (float) $template->goal_weight_percent
                + (float) $template->competency_weight_percent
                + (float) $template->other_weight_percent;

            if (abs($totalWeight - 100.0) > 0.0001) {
                throw new \RuntimeException('Performance appraisal template weights must total 100%.');
            }

            if ($template->effective_from !== null && $template->effective_to !== null && Carbon::parse($template->effective_from)->gt(Carbon::parse($template->effective_to))) {
                throw new \RuntimeException('Performance appraisal template effective-from date must be on or before effective-to date.');
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'applicable_department_id'
        );
    }

    public function scale(): BelongsTo
    {
        return $this->belongsTo(
            PerformanceRatingScale::class,
            'rating_scale_id'
        );
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(
            PerformancePositionCompetency::class,
            'applicable_position_id'
        );
    }

    //    public function grade(): BelongsTo
    //    {
    //        return $this->belongsTo(
    //            EmployeeGrade::class,
    //            'applicable_grade_id'
    //        );
    //    }

    public function ratingScale(): BelongsTo
    {
        return $this->belongsTo(
            PerformanceRatingScale::class,
            'rating_scale_id'
        );
    }

    public function sections(): HasMany
    {
        return $this->hasMany(
            PerformanceAppraisalTemplateSection::class
        )->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(
            PerformanceAppraisalCycleAssignment::class,
            'appraisal_template_id'
        );
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(
            PerformanceAppraisal::class,
            'appraisal_template_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
