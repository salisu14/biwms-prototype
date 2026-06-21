<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyBuffer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'currency_id',
        'buffer_type',
        'entity_id',
        'amount_lcy',
        'amount_fcy',
        'remaining_amount_lcy',
        'remaining_amount_fcy',
        'original_exch_rate',
        'current_exch_rate',
        'unrealized_gain_loss',
        'adjusted',
        'posting_date',
        'due_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount_lcy' => 'decimal:4',
        'amount_fcy' => 'decimal:4',
        'remaining_amount_lcy' => 'decimal:4',
        'remaining_amount_fcy' => 'decimal:4',
        'original_exch_rate' => 'decimal:6',
        'current_exch_rate' => 'decimal:6',
        'unrealized_gain_loss' => 'decimal:4',
        'adjusted' => 'boolean',
        'posting_date' => 'date',
        'due_date' => 'date',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the currency associated with this buffer entry.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Note: The migration indicates this is polymorphic ('entity_id'),
     * but 'entity_type' is missing in the schema.
     * If using standard Laravel polymorphism, you would define:
     * return $this->morphTo('entity');
     */

    // ==================== SCOPES ====================

    public function scopeUnadjusted($query)
    {
        return $query->where('adjusted', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('buffer_type', $type);
    }

    public function scopeReceivables($query)
    {
        return $query->where('buffer_type', 'receivable');
    }

    public function scopePayables($query)
    {
        return $query->where('buffer_type', 'payable');
    }

    // ==================== BUSINESS LOGIC ====================

    /**
     * Calculate the theoretical Unrealized Gain/Loss based on a new exchange rate.
     * * Formula: (Remaining FCY * New Rate) - Remaining LCY
     */
    public function calculateUnrealizedGainLoss(float $newExchangeRate): float
    {
        $newValuationLcy = (float) $this->remaining_amount_fcy * $newExchangeRate;

        // For payables, a higher rate means a loss (liability increases)
        // For receivables, a higher rate means a gain (asset increases)
        $diff = $newValuationLcy - (float) $this->remaining_amount_lcy;

        return $this->buffer_type === 'payable' ? -$diff : $diff;
    }

    /**
     * Mark this buffer as adjusted after a currency revaluation process.
     */
    public function markAsAdjusted(float $newRate, float $gainLoss): void
    {
        $this->update([
            'current_exch_rate' => $newRate,
            'unrealized_gain_loss' => $gainLoss,
            'adjusted' => true,
        ]);
    }
}
