<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyExchangeRateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_id',
        'starting_date',
        'ending_date',
        'exchange_rate_amount',
        'relational_exch_rate_amount',
        'adjustment_exch_rate_amount',
        'rate_type',
        'source',
        'source_reference',
        'is_current',
    ];

    protected $casts = [
        'starting_date' => 'date',
        'ending_date' => 'date',
        'exchange_rate_amount' => 'decimal:6',
        'relational_exch_rate_amount' => 'decimal:6',
        'adjustment_exch_rate_amount' => 'decimal:6',
        'rate_type' => CurrencyExchangeRateType::class,
        'is_current' => 'boolean',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    protected static function booted(): void
    {
        static::saving(function ($rate) {
            // Close previous current rate when new one is added
            if ($rate->is_current) {
                static::where('currency_id', $rate->currency_id)
                    ->where('id', '!=', $rate->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false, 'ending_date' => $rate->starting_date]);
            }
        });
    }

    /**
     * Calculate rate between two dates (for gain/loss)
     */
    public function calculateGainLoss(float $amount, float $newRate): float
    {
        $oldLCY = $amount * $this->exchange_rate_amount;
        $newLCY = $amount * $newRate;

        return $newLCY - $oldLCY;
    }
}
