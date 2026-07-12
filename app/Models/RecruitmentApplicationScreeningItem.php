<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentApplicationScreeningItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weight_percent' => 'decimal:4',
        'is_mandatory' => 'boolean',
        'disqualifying_if_failed' => 'boolean',
        'passed' => 'boolean',
        'score' => 'decimal:4',
    ];

    public function screening(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplicationScreening::class, 'recruitment_application_screening_id');
    }
}
