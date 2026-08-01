<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionSettlementBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class CommissionSettlementBatch extends Model
{
    protected $fillable = [
        'business_id',
        'settlement_number',
        'commission_review_period_id',
        'commission_review_batch_id',
        'currency_code',
        'status',
        'settlement_date',
        'cutoff_date',
        'description',
        'prepared_at',
        'prepared_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'locked_at',
        'locked_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'total_gross_amount',
        'total_hold_amount',
        'total_forfeiture_amount',
        'total_adjustment_amount',
        'total_net_amount',
        'referrer_count',
        'line_count',
        'idempotency_key',
        'snapshot_version',
        'metadata',
    ];

    protected $casts = [
        'status' => CommissionSettlementBatchStatus::class,
        'settlement_date' => 'date',
        'cutoff_date' => 'date',
        'prepared_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'rejected_at' => 'datetime',
        'total_gross_amount' => 'decimal:4',
        'total_hold_amount' => 'decimal:4',
        'total_forfeiture_amount' => 'decimal:4',
        'total_adjustment_amount' => 'decimal:4',
        'total_net_amount' => 'decimal:4',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (CommissionSettlementBatch $batch): void {
            if ($batch->getOriginal('status') === CommissionSettlementBatchStatus::Locked->value) {
                throw new RuntimeException('Locked commission settlement batches cannot be modified.');
            }
        });
    }

    public function reviewBatch(): BelongsTo
    {
        return $this->belongsTo(CommissionReviewBatch::class, 'commission_review_batch_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommissionSettlementLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CommissionSettlementAllocation::class);
    }

    public function liabilityPosting(): HasMany
    {
        return $this->hasMany(CommissionLiabilityPosting::class);
    }

    public function paymentBatches(): HasMany
    {
        return $this->hasMany(CommissionPaymentBatch::class);
    }
}
