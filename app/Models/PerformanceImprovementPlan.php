<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PerformanceImprovementPlan extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUCCESSFULLY_COMPLETED = 'successfully_completed';

    public const STATUS_UNSUCCESSFULLY_COMPLETED = 'unsuccessfully_completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'employee_acknowledged_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformanceImprovementPlan $plan): void {
            if ($plan->end_date !== null && Carbon::parse($plan->end_date)->lt(Carbon::parse($plan->start_date))) {
                throw new \RuntimeException('Performance improvement plan end date must not be before start date.');
            }
        });
    }
}
