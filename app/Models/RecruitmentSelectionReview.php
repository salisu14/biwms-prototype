<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentSelectionReview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(RecruitmentVacancy::class, 'recruitment_vacancy_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(RecruitmentSelectionReviewCandidate::class);
    }
}
