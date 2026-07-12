<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentCandidate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'total_experience_years' => 'decimal:2',
        'consent_given_at' => 'datetime',
        'data_retention_until' => 'date',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RecruitmentCandidateDocument::class);
    }

    public function candidateUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    public function referredByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'referred_by_employee_id');
    }

    public function duplicateCandidates()
    {
        return static::query()
            ->whereKeyNot($this->getKey())
            ->whereRaw('lower(email) = ?', [mb_strtolower((string) $this->email)]);
    }
}
