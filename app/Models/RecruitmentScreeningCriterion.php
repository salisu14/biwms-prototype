<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentScreeningCriterion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weight_percent' => 'decimal:4',
        'is_mandatory' => 'boolean',
        'disqualifying_if_failed' => 'boolean',
        'minimum_value' => 'decimal:4',
        'maximum_value' => 'decimal:4',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecruitmentScreeningTemplate::class, 'recruitment_screening_template_id');
    }
}
