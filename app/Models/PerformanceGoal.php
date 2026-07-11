<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PerformanceGoal extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DEFERRED = 'deferred';

    protected $guarded = [];

    protected $casts = [
        'target_value' => 'decimal:4',
        'baseline_value' => 'decimal:4',
        'current_value' => 'decimal:4',
        'weight_percent' => 'decimal:4',
        'start_date' => 'date',
        'due_date' => 'date',
        'progress_percent' => 'decimal:4',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformanceGoal $goal): void {
            if ($goal->start_date !== null && $goal->due_date !== null && Carbon::parse($goal->due_date)->lt(Carbon::parse($goal->start_date))) {
                throw new \RuntimeException('Goal due date must not be before start date.');
            }

            if ($goal->exists && $goal->getOriginal('status') === self::STATUS_APPROVED && $goal->isDirty(['title', 'description', 'weight_percent', 'target_value', 'due_date'])) {
                $goal->status = self::STATUS_PROPOSED;
                $goal->approved_by = null;
                $goal->approved_at = null;
            }
        });
    }

    public function updates(): HasMany
    {
        return $this->hasMany(PerformanceGoalUpdate::class);
    }
}
