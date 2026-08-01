<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionLiabilityPostingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class CommissionLiabilityPosting extends Model
{
    protected static bool $serviceMutationAllowed = false;

    protected $fillable = [
        'business_id',
        'commission_settlement_batch_id',
        'currency_code',
        'posting_date',
        'document_number',
        'status',
        'gross_amount',
        'withholding_amount',
        'net_liability_amount',
        'posting_transaction_id',
        'posted_at',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'reversal_posting_transaction_id',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'status' => CommissionLiabilityPostingStatus::class,
        'gross_amount' => 'decimal:4',
        'withholding_amount' => 'decimal:4',
        'net_liability_amount' => 'decimal:4',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            if (! static::$serviceMutationAllowed) {
                throw new RuntimeException('Commission liability postings can only be changed by posting services.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Commission liability postings are audit records and cannot be deleted.');
        });
    }

    public static function allowServiceMutation(callable $callback): mixed
    {
        static::$serviceMutationAllowed = true;

        try {
            return $callback();
        } finally {
            static::$serviceMutationAllowed = false;
        }
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlementBatch::class, 'commission_settlement_batch_id');
    }

    public function postingTransaction(): BelongsTo
    {
        return $this->belongsTo(PostingTransaction::class, 'posting_transaction_id');
    }

    public function reversalPostingTransaction(): BelongsTo
    {
        return $this->belongsTo(PostingTransaction::class, 'reversal_posting_transaction_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
