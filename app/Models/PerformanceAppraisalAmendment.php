<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceAppraisalAmendment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
        'approved_at' => 'datetime',
    ];
}
