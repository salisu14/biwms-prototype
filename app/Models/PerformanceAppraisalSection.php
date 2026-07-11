<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceAppraisalSection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weight_percent' => 'decimal:4',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PerformanceAppraisalItem::class)->orderBy('id');
    }
}
