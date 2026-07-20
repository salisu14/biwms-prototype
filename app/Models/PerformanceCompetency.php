<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceCompetency extends Model
{
    protected $fillable = [
        'performance_competency_framework_id',
        'parent_competency_id',
        'code',
        'name',
        'description',
        'competency_type',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function framework(): BelongsTo
    {
        return $this->belongsTo(
            PerformanceCompetencyFramework::class,
            'performance_competency_framework_id'
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            PerformanceCompetency::class,
            'parent_competency_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            PerformanceCompetency::class,
            'parent_competency_id'
        );
    }

    public function levels(): HasMany
    {
        return $this->hasMany(
            PerformanceCompetencyLevel::class,
            'performance_competency_id'
        );
    }
}
