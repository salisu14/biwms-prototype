<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PerformanceAppraisalTemplate extends Model
{
    protected $guarded = [];

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
            $total = (float) $template->goal_weight_percent + (float) $template->competency_weight_percent + (float) $template->other_weight_percent;
            if (abs($total - 100.0) > 0.0001) {
                throw new \RuntimeException('Appraisal template component weights must total 100%.');
            }

            if ($template->effective_from !== null && $template->effective_to !== null && Carbon::parse($template->effective_from)->gt(Carbon::parse($template->effective_to))) {
                throw new \RuntimeException('Template effective-from date must be on or before effective-to date.');
            }
        });
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PerformanceAppraisalTemplateSection::class);
    }
}
