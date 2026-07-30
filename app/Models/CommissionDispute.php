<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionDisputeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionDispute extends Model
{
    protected $fillable = [
        'business_id',
        'dispute_number',
        'referrer_id',
        'commission_review_period_id',
        'commission_review_batch_id',
        'commission_review_batch_line_id',
        'commission_ledger_entry_id',
        'source_type',
        'source_id',
        'status',
        'dispute_type',
        'claimed_amount',
        'currency_code',
        'subject',
        'description',
        'raised_at',
        'raised_by',
        'assigned_to',
        'resolved_at',
        'resolved_by',
        'resolution',
        'resolution_code',
        'approved_adjustment_id',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'status' => CommissionDisputeStatus::class,
        'dispute_type' => CommissionDisputeType::class,
        'claimed_amount' => 'decimal:4',
        'raised_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(CommissionReviewBatchLine::class, 'commission_review_batch_line_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(CommissionLedgerEntry::class, 'approved_adjustment_id');
    }
}
