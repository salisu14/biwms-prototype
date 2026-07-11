<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceGoalUpdate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'progress_percent' => 'decimal:4',
        'current_value' => 'decimal:4',
        'update_date' => 'date',
        'manager_verified_at' => 'datetime',
    ];
}
