<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class CommissionSettlementAllocation extends Model
{
    protected $fillable = [
        'business_id',
        'commission_settlement_batch_id',
        'commission_settlement_line_id',
        'commission_ledger_entry_id',
        'allocated_amount',
        'currency_code',
        'allocation_type',
        'idempotency_key',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Commission settlement allocations are snapshot records and cannot be updated.');
        });
    }

    public function settlementLine(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlementLine::class, 'commission_settlement_line_id');
    }

    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlementBatch::class, 'commission_settlement_batch_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(CommissionLedgerEntry::class, 'commission_ledger_entry_id');
    }
}
