<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyExchangeRateType;
use App\Enums\CurrencyRoundingMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'symbol',
        'decimal_places',
        'rounding_method',
        'amount_rounding_precision',
        'unit_amount_rounding_precision',
        'exchange_rate',
        'exchange_rate_date',
        'exchange_rate_type',
        'realized_gains_account_id',
        'realized_losses_account_id',
        'unrealized_gains_account_id',
        'unrealized_losses_account_id',
        'payment_tolerance_percent',
        'max_payment_tolerance_amount',
        'invoice_rounding',
        'invoice_rounding_precision',
        'invoice_rounding_account_id',
        'receivables_account_id',
        'payables_account_id',
        'reporting_currency_code',
        'is_active',
        'is_lcy',
        'iso_numeric_code',
        'iso_country_code',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'rounding_method' => CurrencyRoundingMethod::class,
        'exchange_rate' => 'decimal:6',
        'amount_rounding_precision' => 'decimal:4',
        'unit_amount_rounding_precision' => 'decimal:5',
        'exchange_rate_date' => 'date',
        'exchange_rate_type' => CurrencyExchangeRateType::class,
        'payment_tolerance_percent' => 'decimal:2',
        'max_payment_tolerance_amount' => 'decimal:4',
        'invoice_rounding' => 'boolean',
        'invoice_rounding_precision' => 'decimal:4',
        'is_active' => 'boolean',
        'is_lcy' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function ($currency) {
            // Ensure only one LCY exists
            if ($currency->is_lcy) {
                static::where('id', '!=', $currency->id)
                    ->where('is_lcy', true)
                    ->update(['is_lcy' => false]);
            }
        });
    }

    // Relationships
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(CurrencyExchangeRate::class)->orderByDesc('starting_date');
    }

    public function buffers(): HasMany
    {
        return $this->hasMany(CurrencyBuffer::class);
    }

    public function currentExchangeRate(): ?CurrencyExchangeRate
    {
        return $this->exchangeRates()->where('is_current', true)->first();
    }

    public function realizedGainsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'realized_gains_account_id');
    }

    public function realizedLossesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'realized_losses_account_id');
    }

    public function unrealizedGainsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'unrealized_gains_account_id');
    }

    public function unrealizedLossesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'unrealized_losses_account_id');
    }

    public function invoiceRoundingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'invoice_rounding_account_id');
    }

    public function receivablesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'receivables_account_id');
    }

    public function payablesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payables_account_id');
    }

    public function adjustmentLedger(): HasMany
    {
        return $this->hasMany(CurrencyAdjustmentLedger::class);
    }

    public function expenseTransactions(): HasMany
    {
        return $this->hasMany(ExpenseTransaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForeign($query)
    {
        return $query->where('is_lcy', false);
    }

    public function scopeWithCurrentRate($query)
    {
        return $query->with(['currentExchangeRate']);
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->description}";
    }

    public function getFormatMaskAttribute(): string
    {
        $decimals = $this->decimal_places;

        return '#,##0.'.str_repeat('0', $decimals);
    }

    // Methods
    public function isLCY(): bool
    {
        return $this->is_lcy;
    }

    /**
     * Round amount according to currency rules (BC: Round Amount)
     */
    public function roundAmount(float $amount): float
    {
        return $this->rounding_method->round($amount, $this->amount_rounding_precision);
    }

    /**
     * Round unit amount (price/cost) with higher precision
     */
    public function roundUnitAmount(float $amount): float
    {
        return $this->rounding_method->round($amount, $this->unit_amount_rounding_precision);
    }

    /**
     * Format amount for display
     */
    public function formatAmount(float $amount): string
    {
        return $this->symbol.' '.number_format($amount, $this->decimal_places);
    }

    /**
     * Get exchange rate for date (BC: Find Exch. Rate)
     */
    public function getExchangeRate(?\DateTime $date = null, ?CurrencyExchangeRateType $type = null): float
    {
        if ($this->is_lcy) {
            return 1;
        }

        $date = $date ?? now();
        $type = $type ?? $this->exchange_rate_type;

        $rate = $this->exchangeRates()
            ->where('starting_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('ending_date')
                    ->orWhere('ending_date', '>=', $date);
            })
            ->where('rate_type', $type)
            ->orderByDesc('starting_date')
            ->first();

        return $rate?->exchange_rate_amount ?? $this->exchange_rate ?? 1;
    }

    /**
     * Convert from LCY to this currency (BC: LCY to FCY)
     */
    public function fromLCY(float $amountLCY, ?float $exchangeRate = null): float
    {
        if ($this->is_lcy) {
            return $amountLCY;
        }

        $rate = $exchangeRate ?? $this->getExchangeRate();

        if ($rate == 0) {
            throw new \InvalidArgumentException("Exchange rate cannot be zero for {$this->code}");
        }

        return $this->roundAmount($amountLCY / $rate);
    }

    /**
     * Convert to LCY from this currency (BC: FCY to LCY)
     */
    public function toLCY(float $amountFCY, ?float $exchangeRate = null): float
    {
        if ($this->is_lcy) {
            return $amountFCY;
        }

        $rate = $exchangeRate ?? $this->getExchangeRate();

        return $this->roundAmount($amountFCY * $rate);
    }

    /**
     * Convert between two foreign currencies via LCY
     */
    public static function convertBetween(
        float $amount,
        Currency $fromCurrency,
        Currency $toCurrency,
        ?\DateTime $date = null
    ): float {
        // Convert to LCY first
        $amountLCY = $fromCurrency->toLCY($amount, null, $date);

        // Then to target currency
        return $toCurrency->fromLCY($amountLCY, null, $date);
    }

    /**
     * Check if amount is within payment tolerance
     */
    public function isWithinTolerance(float $amount, float $expected): bool
    {
        $diff = abs($amount - $expected);

        // Percentage tolerance
        if ($this->payment_tolerance_percent > 0) {
            $percentTolerance = $expected * ($this->payment_tolerance_percent / 100);
            if ($diff <= $percentTolerance) {
                return true;
            }
        }

        // Fixed amount tolerance
        if ($this->max_payment_tolerance_amount && $diff <= $this->max_payment_tolerance_amount) {
            return true;
        }

        return false;
    }

    /**
     * Apply invoice rounding if enabled
     */
    public function roundInvoice(float $amount): array
    {
        if (! $this->invoice_rounding || ! $this->invoice_rounding_precision) {
            return ['rounded' => $amount, 'difference' => 0];
        }

        $rounded = $this->rounding_method->round($amount, $this->invoice_rounding_precision);

        return [
            'rounded' => $rounded,
            'difference' => $this->roundAmount($rounded - $amount),
        ];
    }
}
