<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PerformancePositionCompetency extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expected_level' => 'integer',
        'weight_percent' => 'decimal:4',
        'is_required' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformancePositionCompetency $requirement): void {
            if ($requirement->effective_to !== null && Carbon::parse($requirement->effective_to)->lt(Carbon::parse($requirement->effective_from))) {
                throw new \RuntimeException('Position competency effective-to date must not be before effective-from date.');
            }
        });
    }
}
