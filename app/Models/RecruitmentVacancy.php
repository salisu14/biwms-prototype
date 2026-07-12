<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentVacancy extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'opening_date' => 'date',
        'target_closing_date' => 'date',
        'actual_closing_date' => 'date',
        'salary_min' => 'decimal:4',
        'salary_max' => 'decimal:4',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (RecruitmentVacancy $vacancy): void {
            if ((int) $vacancy->number_of_openings <= 0) {
                throw new \RuntimeException('Vacancy openings must be greater than zero.');
            }

            if ((int) $vacancy->filled_openings > (int) $vacancy->number_of_openings) {
                throw new \RuntimeException('Filled openings cannot exceed vacancy openings.');
            }

            if ($vacancy->salary_min !== null && $vacancy->salary_max !== null && (float) $vacancy->salary_max < (float) $vacancy->salary_min) {
                throw new \RuntimeException('Vacancy salary maximum cannot be below minimum.');
            }
        });
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(RecruitmentRequisition::class, 'recruitment_requisition_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class);
    }

    public function postings(): HasMany
    {
        return $this->hasMany(RecruitmentJobPosting::class);
    }

    public function remainingOpenings(): int
    {
        return max(0, (int) $this->number_of_openings - (int) $this->filled_openings);
    }

    public function acceptsApplications(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->visibility !== 'confidential';
    }
}
