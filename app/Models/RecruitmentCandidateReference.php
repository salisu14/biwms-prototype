<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentCandidateReference extends Model
{
    protected $guarded = [];

    protected $casts = [
        'years_known' => 'decimal:2',
        'consent_confirmed' => 'boolean',
        'verified_at' => 'datetime',
    ];
}
