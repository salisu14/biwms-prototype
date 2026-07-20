<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceCompetencyFramework extends Model
{
    protected $fillable = [
        'business_id',
        'code',
        'name',
        'description',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(PerformanceCompetency::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveNow($query)
    {
        $now = now()->toDateString();

        return $query->where(function ($q) use ($now) {
            $q->whereNull('effective_from')
                ->orWhere('effective_from', '&lt;=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('effective_to')
                ->orWhere('effective_to', '&gt;=', $now);
        });
    }
}
