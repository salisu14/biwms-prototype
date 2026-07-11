<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceCompetencyFramework extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function competencies(): HasMany
    {
        return $this->hasMany(PerformanceCompetency::class);
    }
}
