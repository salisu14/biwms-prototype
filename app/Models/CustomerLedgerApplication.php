<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedgerApplication extends Model
{
    protected $fillable = [
        'customer_id',
        'business_id',
        'source_customer_ledger_entry_id',
        'target_customer_ledger_entry_id',
        'source_posted_sales_credit_memo_id',
        'target_posted_sales_invoice_id',
        'amount',
        'currency_code',
        'currency_id',
        'source_remaining_before',
        'source_remaining_after',
        'target_remaining_before',
        'target_remaining_after',
        'applied_at',
        'applied_by',
        'reversed',
        'reversed_at',
        'reversed_by',
        'reversal_reference',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'currency_id' => 'integer',
        'business_id' => 'integer',
        'source_remaining_before' => 'decimal:4',
        'source_remaining_after' => 'decimal:4',
        'target_remaining_before' => 'decimal:4',
        'target_remaining_after' => 'decimal:4',
        'applied_at' => 'datetime',
        'reversed' => 'boolean',
        'reversed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerLedgerApplication $application): void {
            $sourceBusinessId = $application->sourceCreditMemo?->business_id;
            $targetBusinessId = $application->targetInvoice?->business_id;

            if ($sourceBusinessId !== null && $targetBusinessId !== null && (int) $sourceBusinessId !== (int) $targetBusinessId) {
                throw new BusinessException('Customer ledger applications must remain within one business.');
            }

            $application->business_id ??= $sourceBusinessId ?? $targetBusinessId;
        });

        static::updating(function (): void {
            throw new BusinessException('Customer ledger application records are immutable. Create a reversing application record instead.');
        });

        static::deleting(function (): void {
            throw new BusinessException('Customer ledger application records are immutable and cannot be deleted.');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sourceLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerLedgerEntry::class, 'source_customer_ledger_entry_id');
    }

    public function targetLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerLedgerEntry::class, 'target_customer_ledger_entry_id');
    }

    public function sourceCreditMemo(): BelongsTo
    {
        return $this->belongsTo(PostedSalesCreditMemo::class, 'source_posted_sales_credit_memo_id');
    }

    public function targetInvoice(): BelongsTo
    {
        return $this->belongsTo(PostedSalesInvoice::class, 'target_posted_sales_invoice_id');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
