<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentInterviewScore extends Model
{
    protected $guarded = [];

    protected $casts = [
        'total_score' => 'decimal:4',
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (RecruitmentInterviewScore $score): void {
            if ($score->exists && in_array($score->getOriginal('status'), ['submitted', 'locked'], true) && $score->isDirty()) {
                throw new \RuntimeException('Submitted interview scores are locked.');
            }
        });
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(RecruitmentInterview::class, 'recruitment_interview_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecruitmentInterviewScoreItem::class);
    }
}
