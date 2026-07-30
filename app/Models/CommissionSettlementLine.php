<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionSettlementLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionSettlementLine extends Model
{
    protected $fillable = [
        'business_id',
        'commission_settlement_batch_id',
        'commission_review_batch_id',
        'commission_review_batch_line_id',
        'referrer_id',
        'currency_code',
        'gross_amount',
        'hold_amount',
        'forfeiture_amount',
        'adjustment_amount',
        'net_settlement_amount',
        'status',
        'exception_code',
        'exception_message',
        'snapshot',
        'idempotency_key',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:4',
        'hold_amount' => 'decimal:4',
        'forfeiture_amount' => 'decimal:4',
        'adjustment_amount' => 'decimal:4',
        'net_settlement_amount' => 'decimal:4',
        'status' => CommissionSettlementLineStatus::class,
        'snapshot' => 'array',
    ];

    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlementBatch::class, 'commission_settlement_batch_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CommissionSettlementAllocation::class);
    }
}
