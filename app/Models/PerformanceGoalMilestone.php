<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceGoalMilestone extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'target_value' => 'decimal:4',
        'weight_percent' => 'decimal:4',
        'completed_at' => 'datetime',
    ];
}
