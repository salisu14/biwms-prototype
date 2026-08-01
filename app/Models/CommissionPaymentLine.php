<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionPaymentLineStatus;
use App\Enums\CommissionPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class CommissionPaymentLine extends Model
{
    protected static bool $serviceMutationAllowed = false;

    protected $fillable = [
        'business_id',
        'commission_payment_batch_id',
        'commission_settlement_batch_id',
        'commission_settlement_line_id',
        'referrer_id',
        'currency_code',
        'approved_amount',
        'previously_paid_amount',
        'payment_amount',
        'remaining_amount',
        'payment_method',
        'beneficiary_name',
        'masked_payment_reference',
        'external_reference',
        'status',
        'exception_code',
        'exception_message',
        'posting_transaction_id',
        'idempotency_key',
        'snapshot',
    ];

    protected $casts = [
        'approved_amount' => 'decimal:4',
        'previously_paid_amount' => 'decimal:4',
        'payment_amount' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
        'payment_method' => CommissionPaymentMethod::class,
        'status' => CommissionPaymentLineStatus::class,
        'snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            if (! static::$serviceMutationAllowed) {
                throw new RuntimeException('Commission payment lines can only be changed by payment services.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Commission payment lines are audit records and cannot be deleted.');
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CommissionPaymentBatch::class, 'commission_payment_batch_id');
    }

    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlementBatch::class, 'commission_settlement_batch_id');
    }

    public function settlementLine(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlementLine::class, 'commission_settlement_line_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CommissionPaymentApplication::class);
    }
}
