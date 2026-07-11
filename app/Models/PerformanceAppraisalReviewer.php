<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceAppraisalReviewer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'can_rate' => 'boolean',
        'can_comment' => 'boolean',
        'is_confidential' => 'boolean',
        'invited_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];
}
