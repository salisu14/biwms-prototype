<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionReviewPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class CommissionReviewPeriod extends Model
{
    protected $fillable = [
        'business_id',
        'code',
        'name',
        'period_start',
        'period_end',
        'status',
        'currency_mode',
        'description',
        'opened_at',
        'opened_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'locked_at',
        'locked_by',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'status' => CommissionReviewPeriodStatus::class,
        'opened_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'reopened_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (CommissionReviewPeriod $period): void {
            if ($period->getOriginal('status') === CommissionReviewPeriodStatus::Locked->value && $period->status !== CommissionReviewPeriodStatus::Reopened) {
                throw new RuntimeException('Locked commission review periods cannot be modified.');
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(CommissionReviewBatch::class);
    }

    public function settlementBatches(): HasMany
    {
        return $this->hasMany(CommissionSettlementBatch::class);
    }
}
