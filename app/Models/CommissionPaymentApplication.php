<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionPaymentApplicationStatus;
use App\Enums\CommissionPaymentApplicationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class CommissionPaymentApplication extends Model
{
    protected $fillable = [
        'business_id',
        'commission_payment_batch_id',
        'commission_payment_line_id',
        'commission_settlement_allocation_id',
        'commission_ledger_entry_id',
        'referrer_id',
        'currency_code',
        'applied_amount',
        'application_type',
        'status',
        'reverses_application_id',
        'reversed_by_application_id',
        'posting_date',
        'idempotency_key',
        'created_by',
    ];

    protected $casts = [
        'applied_amount' => 'decimal:4',
        'application_type' => CommissionPaymentApplicationType::class,
        'status' => CommissionPaymentApplicationStatus::class,
        'posting_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Commission payment applications are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Commission payment applications are append-only and cannot be deleted.');
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CommissionPaymentBatch::class, 'commission_payment_batch_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(CommissionPaymentLine::class, 'commission_payment_line_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlementAllocation::class, 'commission_settlement_allocation_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(CommissionLedgerEntry::class, 'commission_ledger_entry_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function reversesApplication(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_application_id');
    }

    public function reversedByApplication(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_application_id');
    }
}
