<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PerformanceRatingScale extends Model
{
    protected $guarded = [];

    protected $casts = [
        'minimum_score' => 'decimal:4',
        'maximum_score' => 'decimal:4',
        'decimal_places' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformanceRatingScale $scale): void {
            if ((float) $scale->minimum_score > (float) $scale->maximum_score) {
                throw new \RuntimeException('Rating scale minimum score must be less than or equal to maximum score.');
            }

            if ($scale->effective_from !== null && $scale->effective_to !== null && Carbon::parse($scale->effective_from)->gt(Carbon::parse($scale->effective_to))) {
                throw new \RuntimeException('Rating scale effective-from date must be on or before effective-to date.');
            }

            if ($scale->is_default && $scale->is_active) {
                $overlapExists = self::query()
                    ->when($scale->exists, fn ($query) => $query->whereKeyNot($scale->getKey()))
                    ->where('business_id', $scale->business_id)
                    ->where('is_default', true)
                    ->where('is_active', true)
                    ->where(function ($query) use ($scale): void {
                        $from = $scale->effective_from?->toDateString() ?? '0001-01-01';
                        $to = $scale->effective_to?->toDateString() ?? '9999-12-31';

                        $query->whereDate('effective_from', '<=', $to)
                            ->where(function ($inner) use ($from): void {
                                $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
                            });
                    })
                    ->exists();

                if ($overlapExists) {
                    throw new \RuntimeException('Only one active default rating scale can apply to a business/date range.');
                }
            }
        });
    }

    public function levels(): HasMany
    {
        return $this->hasMany(PerformanceRatingScaleLevel::class);
    }

    public function levelForScore(float $score): ?PerformanceRatingScaleLevel
    {
        return $this->levels()
            ->where('score_from', '<=', $score)
            ->where('score_to', '>=', $score)
            ->orderBy('sort_order')
            ->first();
    }
}
