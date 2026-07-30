<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionReviewBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class CommissionReviewBatch extends Model
{
    protected $fillable = [
        'business_id',
        'commission_review_period_id',
        'batch_number',
        'currency_code',
        'status',
        'referrer_scope',
        'calculation_date',
        'cutoff_date',
        'generated_at',
        'generated_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'locked_at',
        'locked_by',
        'total_accrual_amount',
        'total_adjustment_amount',
        'total_reversal_amount',
        'total_hold_amount',
        'total_forfeiture_amount',
        'total_eligible_amount',
        'line_count',
        'exception_count',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'status' => CommissionReviewBatchStatus::class,
        'calculation_date' => 'date',
        'cutoff_date' => 'date',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'locked_at' => 'datetime',
        'total_accrual_amount' => 'decimal:4',
        'total_adjustment_amount' => 'decimal:4',
        'total_reversal_amount' => 'decimal:4',
        'total_hold_amount' => 'decimal:4',
        'total_forfeiture_amount' => 'decimal:4',
        'total_eligible_amount' => 'decimal:4',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (CommissionReviewBatch $batch): void {
            if ($batch->getOriginal('status') === CommissionReviewBatchStatus::Locked->value && $batch->status !== CommissionReviewBatchStatus::Rejected) {
                throw new RuntimeException('Locked commission review batches cannot be modified.');
            }
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(CommissionReviewPeriod::class, 'commission_review_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommissionReviewBatchLine::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(CommissionHold::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(CommissionDispute::class);
    }

    public function settlementBatches(): HasMany
    {
        return $this->hasMany(CommissionSettlementBatch::class);
    }
}
