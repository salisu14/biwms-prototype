<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\BusinessException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubledgerOpeningBalance extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_POSTED = 'POSTED';

    public const STATUS_REVERSED = 'REVERSED';

    private static bool $allowServiceTransition = false;

    protected $fillable = [
        'business_id', 'party_type', 'customer_id', 'vendor_id', 'document_number',
        'external_document_number', 'original_document_type', 'posting_date',
        'document_date', 'due_date', 'currency_id', 'currency_code', 'original_amount',
        'currency_factor', 'amount_lcy', 'remaining_amount', 'remaining_amount_lcy',
        'control_account_id', 'opening_equity_account_id', 'general_business_posting_group_id',
        'customer_posting_group_id', 'vendor_posting_group_id', 'description',
        'source_type', 'source_id', 'dimensions', 'status', 'created_by', 'posted_by',
        'posted_at', 'reversed_by', 'reversed_at', 'reversal_of_id',
        'customer_ledger_entry_id', 'vendor_ledger_entry_id', 'posting_transaction_id',
        'idempotency_key',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'due_date' => 'date',
        'original_amount' => 'decimal:6',
        'currency_factor' => 'decimal:8',
        'amount_lcy' => 'decimal:4',
        'remaining_amount' => 'decimal:6',
        'remaining_amount_lcy' => 'decimal:4',
        'dimensions' => 'array',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $opening): void {
            if (in_array($opening->getOriginal('status'), [self::STATUS_POSTED, self::STATUS_REVERSED], true)
                && ! self::$allowServiceTransition
                && $opening->isDirty()) {
                throw new BusinessException('Posted opening balance facts are immutable. Use reversal for corrections.');
            }
        });

        static::deleting(function (self $opening): void {
            if ($opening->status !== self::STATUS_DRAFT) {
                throw new BusinessException('Posted opening balances cannot be deleted.');
            }
        });
    }

    public static function allowServiceTransition(callable $callback): mixed
    {
        $previous = self::$allowServiceTransition;
        self::$allowServiceTransition = true;

        try {
            return $callback();
        } finally {
            self::$allowServiceTransition = $previous;
        }
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function controlAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'control_account_id');
    }

    public function openingEquityAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'opening_equity_account_id');
    }

    public function customerLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerLedgerEntry::class);
    }

    public function vendorLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(VendorLedgerEntry::class);
    }

    public function postingTransaction(): BelongsTo
    {
        return $this->belongsTo(PostingTransaction::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
