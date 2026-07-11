<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceAppraisalTemplateItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weight_percent' => 'decimal:4',
        'is_required' => 'boolean',
        'allow_not_applicable' => 'boolean',
        'sort_order' => 'integer',
    ];
}
