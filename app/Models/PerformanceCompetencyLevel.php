<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceCompetencyLevel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'level_number' => 'integer',
        'behavioural_indicators' => 'array',
    ];
}
