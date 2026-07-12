<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentInterviewScoreItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weight_percent' => 'decimal:4',
        'maximum_score' => 'decimal:4',
        'score' => 'decimal:4',
    ];
}
