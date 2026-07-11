<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PerformanceProbationReview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'probation_start_date' => 'date',
        'expected_confirmation_date' => 'date',
        'review_date' => 'date',
        'performance_score' => 'decimal:4',
        'attendance_context' => 'array',
        'recommended_extension_end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PerformanceProbationReview $review): void {
            if ($review->recommended_extension_end_date !== null && Carbon::parse($review->recommended_extension_end_date)->lte(Carbon::parse($review->review_date))) {
                throw new \RuntimeException('Recommended probation extension date must be after the review date.');
            }
        });
    }
}
