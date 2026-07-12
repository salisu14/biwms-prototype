<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentInterview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'candidate_confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (RecruitmentInterview $interview): void {
            if ($interview->scheduled_end !== null && $interview->scheduled_start !== null && $interview->scheduled_end->lte($interview->scheduled_start)) {
                throw new \RuntimeException('Interview end time must be after start time.');
            }
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(RecruitmentInterviewPanel::class, 'recruitment_interview_panel_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(RecruitmentInterviewScore::class);
    }
}
