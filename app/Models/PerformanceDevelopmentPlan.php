<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PerformanceDevelopmentPlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformanceDevelopmentPlan $plan): void {
            if ($plan->target_completion_date !== null && Carbon::parse($plan->target_completion_date)->lt(Carbon::parse($plan->start_date))) {
                throw new \RuntimeException('Development plan target completion date must not be before start date.');
            }
        });
    }
}
