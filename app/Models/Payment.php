<?php

// app/Models/Payment.php

namespace App\Models;

use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Payment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (! empty($payment->payment_number)) {
                return;
            }

            try {
                $payment->payment_number = app(NumberSeriesService::class)->getNextNo('PAYMENT');
            } catch (\Throwable) {
                $payment->payment_number = self::generateNumber((string) ($payment->payment_direction ?: 'DISBURSEMENT'));
            }
        });

        static::creating(function (Payment $payment): void {
            if (empty($payment->currency_code) && ! empty($payment->currency_id)) {
                $payment->currency_code = Currency::query()
                    ->whereKey($payment->currency_id)
                    ->value('code');
            }

            $payment->currency_code ??= 'NGN';
            $payment->created_by ??= Auth::id() ?? 1;
            $payment->applied_amount ??= 0;
            $payment->unapplied_amount ??= (float) ($payment->payment_amount ?? 0) - (float) ($payment->applied_amount ?? 0);
            $payment->discount_taken ??= 0;
            $payment->transaction_fee ??= 0;
            $payment->transaction_fee_lcy ??= 0;
            $payment->currency_factor ??= 1;
            $payment->payment_amount_lcy ??= (float) ($payment->payment_amount ?? 0) * (float) $payment->currency_factor;
            $payment->status ??= 'PENDING';
        });
    }

    protected $fillable = [
        'payment_number',
        'external_reference',
        'payment_direction',
        'party_type',
        'party_id',
        'party_name',
        'payment_method',
        'bank_account_id',
        'currency_id',
        'bank_account_number',
        'check_number',
        'check_date',
        'counterparty_bank_name',
        'counterparty_account_number',
        'counterparty_routing_number',
        'payment_amount',
        'applied_amount',
        'unapplied_amount',
        'currency_code',
        'currency_factor',
        'payment_amount_lcy',
        'discount_taken',
        'discount_reason',
        'transaction_fee',
        'transaction_fee_lcy',
        'payment_date',
        'posting_date',
        'value_date',
        'clearing_date',
        'status',
        'reconciled',
        'reconciled_at',
        'reconciled_by',
        'bank_statement_line_id',
        'general_business_posting_group_id',
        'posting_group_id',
        'created_by',
        'posted_by',
        'posted_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'internal_notes',
        'memo',
        'dimensions',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:4',
        'applied_amount' => 'decimal:4',
        'unapplied_amount' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'payment_amount_lcy' => 'decimal:4',
        'discount_taken' => 'decimal:4',
        'transaction_fee' => 'decimal:4',
        'transaction_fee_lcy' => 'decimal:4',
        'payment_date' => 'date',
        'posting_date' => 'date',
        'value_date' => 'date',
        'clearing_date' => 'date',
        'reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
        'dimensions' => 'array',
        'currency_id' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================

    public function party()
    {
        return match ($this->party_type) {
            'CUSTOMER' => $this->belongsTo(Customer::class, 'party_id'),
            'VENDOR' => $this->belongsTo(Vendor::class, 'party_id'),
            default => null,
        };
    }

    public function customer(): ?BelongsTo
    {
        return $this->party_type === 'CUSTOMER'
            ? $this->belongsTo(Customer::class, 'party_id')
            : null;
    }

    public function vendor(): ?BelongsTo
    {
        return $this->party_type === 'VENDOR'
            ? $this->belongsTo(Vendor::class, 'party_id')
            : null;
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    // Ledger entries created by this payment
    public function ledgerEntries(): HasMany
    {
        $model = $this->payment_direction === 'RECEIPT'
            ? CustomerLedgerEntry::class
            : VendorLedgerEntry::class;

        return $this->hasMany($model, 'document_number', 'payment_number');
    }

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'source_number', 'payment_number')
            ->where('document_type', 'PAYMENT');
    }

    // ==================== SCOPES ====================

    public function scopeReceipts($query)
    {
        return $query->where('payment_direction', 'RECEIPT');
    }

    public function scopeDisbursements($query)
    {
        return $query->where('payment_direction', 'DISBURSEMENT');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'POSTED');
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('reconciled', false);
    }

    public function scopeForParty($query, string $type, int $id)
    {
        return $query->where('party_type', $type)->where('party_id', $id);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getIsFullyAppliedAttribute(): bool
    {
        return $this->unapplied_amount <= 0.01;
    }

    public function getIsPartiallyAppliedAttribute(): bool
    {
        return $this->applied_amount > 0 && ! $this->is_fully_applied;
    }

    public function getIsOnAccountAttribute(): bool
    {
        return $this->applied_amount == 0;
    }

    public function getNetAmountAttribute(): float
    {
        return $this->payment_amount - $this->transaction_fee;
    }

    // ==================== STATIC HELPERS ====================

    /**
     * Generate payment number
     */
    public static function generateNumber(string $direction): string
    {
        $prefix = $direction === 'RECEIPT' ? 'REC' : 'DIS';
        $year = date('Y');
        $count = self::where('payment_direction', $direction)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }
}
