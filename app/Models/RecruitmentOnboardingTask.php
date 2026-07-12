<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentOnboardingTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'is_required' => 'boolean',
        'requires_attachment' => 'boolean',
        'requires_approval' => 'boolean',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RecruitmentOnboardingPlan::class, 'recruitment_onboarding_plan_id');
    }
}
