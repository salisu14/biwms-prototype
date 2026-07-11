<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceAppraisalModerationItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'original_score' => 'decimal:4',
        'proposed_score' => 'decimal:4',
        'moderated_score' => 'decimal:4',
        'moderated_at' => 'datetime',
    ];
}
