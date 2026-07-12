<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentSelectionReviewCandidate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'screening_score' => 'decimal:4',
        'assessment_score' => 'decimal:4',
        'interview_score' => 'decimal:4',
        'combined_score' => 'decimal:4',
        'conflict_of_interest_declared' => 'boolean',
    ];
}
