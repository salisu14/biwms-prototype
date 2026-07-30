<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionHoldStatus;
use App\Enums\CommissionHoldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionHold extends Model
{
    protected $fillable = [
        'business_id',
        'referrer_id',
        'commission_review_batch_id',
        'commission_review_batch_line_id',
        'commission_ledger_entry_id',
        'hold_type',
        'status',
        'amount',
        'currency_code',
        'reason_code',
        'reason',
        'placed_at',
        'placed_by',
        'released_at',
        'released_by',
        'release_reason',
        'expires_at',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'hold_type' => CommissionHoldType::class,
        'status' => CommissionHoldStatus::class,
        'amount' => 'decimal:4',
        'placed_at' => 'datetime',
        'released_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(CommissionLedgerEntry::class, 'commission_ledger_entry_id');
    }
}
