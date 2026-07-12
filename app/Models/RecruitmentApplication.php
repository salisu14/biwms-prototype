<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentApplication extends Model
{
    public const STAGE_APPLIED = 'applied';

    public const STAGE_SCREENING = 'screening';

    public const STAGE_SHORTLISTED = 'shortlisted';

    public const STAGE_INTERVIEW = 'interview';

    public const STAGE_SELECTION_REVIEW = 'selection_review';

    public const STAGE_OFFER = 'offer';

    public const STAGE_HIRED = 'hired';

    public const STAGE_REJECTED = 'rejected';

    public const STAGE_WITHDRAWN = 'withdrawn';

    public const STAGE_ON_HOLD = 'on_hold';

    protected $guarded = [];

    protected $casts = [
        'application_date' => 'date',
        'expected_salary' => 'decimal:4',
        'available_start_date' => 'date',
        'withdrawn_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'recruitment_candidate_id');
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(RecruitmentVacancy::class, 'recruitment_vacancy_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RecruitmentApplicationStageHistory::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RecruitmentOffer::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(RecruitmentPreEmploymentCheck::class);
    }

    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id');
    }
}
