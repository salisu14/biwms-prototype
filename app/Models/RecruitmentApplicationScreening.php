<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentApplicationScreening extends Model
{
    protected $guarded = [];

    protected $casts = [
        'total_score' => 'decimal:4',
        'mandatory_criteria_passed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (RecruitmentApplicationScreening $screening): void {
            if (filled($screening->override_recommendation) && blank($screening->override_reason)) {
                throw new \RuntimeException('Screening override requires a reason.');
            }
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'recruitment_application_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecruitmentScreeningTemplate::class, 'screening_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecruitmentApplicationScreeningItem::class);
    }
}
