<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class CommissionLedgerEntry extends Model
{
    protected $fillable = [
        'business_id',
        'entry_number',
        'entry_type',
        'referrer_id',
        'customer_id',
        'customer_referral_id',
        'commission_calculation_id',
        'commission_calculation_line_id',
        'source_type',
        'source_id',
        'source_line_id',
        'source_number',
        'posting_date',
        'currency_code',
        'amount',
        'base_amount',
        'status',
        'reverses_entry_id',
        'reversed_by_entry_id',
        'idempotency_key',
        'reason_code',
        'description',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'entry_number' => 'integer',
        'entry_type' => CommissionLedgerEntryType::class,
        'posting_date' => 'date',
        'amount' => 'decimal:4',
        'base_amount' => 'decimal:4',
        'status' => CommissionLedgerEntryStatus::class,
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommissionLedgerEntry $entry): void {
            $entry->entry_number ??= ((int) static::query()->max('entry_number')) + 1;
        });

        static::updating(function (): void {
            throw new RuntimeException('Commission ledger entries are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Commission ledger entries are append-only and cannot be deleted.');
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerReferral(): BelongsTo
    {
        return $this->belongsTo(CustomerReferral::class);
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(CommissionCalculation::class, 'commission_calculation_id');
    }

    public function calculationLine(): BelongsTo
    {
        return $this->belongsTo(CommissionCalculationLine::class, 'commission_calculation_line_id');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function reversalEntries(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
