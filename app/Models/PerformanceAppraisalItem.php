<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceAppraisalItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weight_percent' => 'decimal:4',
        'employee_rating' => 'decimal:4',
        'manager_rating' => 'decimal:4',
        'secondary_reviewer_rating' => 'decimal:4',
        'moderated_rating' => 'decimal:4',
        'final_rating' => 'decimal:4',
        'is_not_applicable' => 'boolean',
    ];
}
