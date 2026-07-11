<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceImprovementPlanObjective extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
    ];
}
