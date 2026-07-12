<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeConfirmationDecision extends Model
{
    protected $guarded = [];

    protected $casts = [
        'proposed_effective_date' => 'date',
        'proposed_extension_end_date' => 'date',
        'approved_at' => 'datetime',
        'implemented_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeConfirmationDecision $decision): void {
            if ($decision->decision_type === 'extend_probation'
                && ($decision->proposed_extension_end_date === null || $decision->proposed_extension_end_date->lte($decision->proposed_effective_date))) {
                throw new \RuntimeException('Probation extension decisions require an extension date after the effective date.');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function probationReview(): BelongsTo
    {
        return $this->belongsTo(PerformanceProbationReview::class, 'performance_probation_review_id');
    }
}
