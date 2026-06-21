<?php

// app/Models/PricingMasterQuantityBreak.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class PricingMasterQuantityBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'pricing_master_id',
        'minimum_quantity',
        'maximum_quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'unit_of_measure_code',
        'line_number',
    ];

    protected $casts = [
        'minimum_quantity' => 'decimal:4',
        'maximum_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:4',
    ];

    public function pricingMaster(): BelongsTo
    {
        return $this->belongsTo(PricingMaster::class);
    }

    // Check if quantity falls in this tier
    public function containsQuantity(float $quantity): bool
    {
        return $quantity >= $this->minimum_quantity &&
            ($this->maximum_quantity === null || $quantity <= $this->maximum_quantity);
    }

    // Get price display text
    public function getTierDescription(?string $currencyCode = null): string
    {
        $max = $this->maximum_quantity ? number_format($this->maximum_quantity) : '+';
        $currencyCode = $currencyCode ?? config('app.default_currency', 'USD');

        if ($this->unit_price !== null) {
            return number_format($this->minimum_quantity).' - '.$max.' @ '.Number::currency((float) $this->unit_price, $currencyCode);
        }

        if ($this->discount_percent !== null) {
            return number_format($this->minimum_quantity).' - '.$max.' @ '.$this->discount_percent.'% off';
        }

        return number_format($this->minimum_quantity).' - '.$max;
    }
}
