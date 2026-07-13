<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceRatingScaleLevel extends Model
{
    protected $fillable = [
        'performance_rating_scale_id',
        'code',
        'name',
        'score_from',
        'score_to',
        'numeric_value',
        'description',
        'color',
        'sort_order',
        'is_passing',
    ];

    protected $casts = [
        'score_from' => 'decimal:4',
        'score_to' => 'decimal:4',
        'numeric_value' => 'decimal:4',
        'sort_order' => 'integer',
        'is_passing' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformanceRatingScaleLevel $level): void {
            if ((float) $level->score_from > (float) $level->score_to) {
                throw new \RuntimeException('Rating scale level score_from must be less than or equal to score_to.');
            }

            $scale = $level->scale;
            if ($scale && ((float) $level->score_from < (float) $scale->minimum_score || (float) $level->score_to > (float) $scale->maximum_score)) {
                throw new \RuntimeException('Rating scale level range must remain within the parent scale range.');
            }

            $overlapExists = self::query()
                ->when($level->exists, fn ($query) => $query->whereKeyNot($level->getKey()))
                ->where('performance_rating_scale_id', $level->performance_rating_scale_id)
                ->where('score_from', '<=', $level->score_to)
                ->where('score_to', '>=', $level->score_from)
                ->exists();

            if ($overlapExists) {
                throw new \RuntimeException('Rating scale level score ranges must not overlap.');
            }
        });
    }

    public function scale(): BelongsTo
    {
        return $this->belongsTo(PerformanceRatingScale::class, 'performance_rating_scale_id');
    }
}
