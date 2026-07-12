<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentAssessment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'maximum_score' => 'decimal:4',
        'passing_score' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
