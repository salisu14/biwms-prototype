<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentOnboardingPlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'progress_percent' => 'decimal:4',
        'completed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RecruitmentOnboardingTask::class);
    }
}
