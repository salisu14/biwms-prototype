<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionPaymentBatchStatus;
use App\Enums\CommissionPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class CommissionPaymentBatch extends Model
{
    protected static bool $serviceMutationAllowed = false;

    protected $fillable = [
        'business_id',
        'batch_number',
        'commission_settlement_batch_id',
        'currency_code',
        'payment_date',
        'posting_date',
        'payment_method',
        'bank_account_id',
        'cash_account_id',
        'status',
        'description',
        'external_reference',
        'prepared_at',
        'prepared_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'reversed_at',
        'reversed_by',
        'total_amount',
        'line_count',
        'referrer_count',
        'posting_transaction_id',
        'idempotency_key',
        'failure_code',
        'failure_message',
        'metadata',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'posting_date' => 'date',
        'payment_method' => CommissionPaymentMethod::class,
        'status' => CommissionPaymentBatchStatus::class,
        'prepared_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reversed_at' => 'datetime',
        'total_amount' => 'decimal:4',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            if (! static::$serviceMutationAllowed) {
                throw new RuntimeException('Commission payment batches can only be changed by payment services.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Commission payment batches are audit records and cannot be deleted.');
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

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'cash_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommissionPaymentLine::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CommissionPaymentApplication::class);
    }

    public function postingTransaction(): BelongsTo
    {
        return $this->belongsTo(PostingTransaction::class, 'posting_transaction_id');
    }
}
