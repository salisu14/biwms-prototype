<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionReviewBatchStatus;
use App\Enums\CommissionReviewLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class CommissionReviewBatchLine extends Model
{
    protected $fillable = [
        'business_id',
        'commission_review_batch_id',
        'referrer_id',
        'currency_code',
        'commission_ledger_entry_id',
        'commission_calculation_id',
        'commission_calculation_line_id',
        'source_type',
        'source_id',
        'source_number',
        'source_posting_date',
        'entry_type',
        'original_amount',
        'eligible_amount',
        'held_amount',
        'forfeited_amount',
        'approved_amount',
        'review_status',
        'exception_status',
        'exception_code',
        'exception_message',
        'review_notes',
        'reviewed_at',
        'reviewed_by',
        'approved_at',
        'approved_by',
        'snapshot',
        'idempotency_key',
    ];

    protected $casts = [
        'source_posting_date' => 'date',
        'original_amount' => 'decimal:4',
        'eligible_amount' => 'decimal:4',
        'held_amount' => 'decimal:4',
        'forfeited_amount' => 'decimal:4',
        'approved_amount' => 'decimal:4',
        'review_status' => CommissionReviewLineStatus::class,
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (CommissionReviewBatchLine $line): void {
            if ($line->batch?->status === CommissionReviewBatchStatus::Locked) {
                throw new RuntimeException('Lines in locked commission review batches cannot be modified.');
            }
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CommissionReviewBatch::class, 'commission_review_batch_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(CommissionLedgerEntry::class, 'commission_ledger_entry_id');
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(CommissionCalculation::class, 'commission_calculation_id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(CommissionHold::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(CommissionDispute::class);
    }
}
