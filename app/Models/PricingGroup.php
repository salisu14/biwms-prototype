<?php

// app/Models/PricingGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'pricing_strategy',
        'default_discount_percent',
        'default_markup_percent',
        'allow_manual_override',
        'enforce_minimum_margin',
        'minimum_margin_percent',
        'currency_code',
        'start_date',
        'end_date',
        'general_business_posting_group_id',
        'blocked',
    ];

    protected $casts = [
        'default_discount_percent' => 'decimal:2',
        'default_markup_percent' => 'decimal:2',
        'allow_manual_override' => 'boolean',
        'enforce_minimum_margin' => 'boolean',
        'minimum_margin_percent' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'blocked' => 'boolean',
    ];

    // Relationships
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function pricingMasterEntries(): HasMany
    {
        return $this->hasMany(PricingMaster::class, 'pricing_group_id');
    }

    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    // Check if group is currently active
    public function isActive(): bool
    {
        if ($this->blocked) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->start_date && $today < $this->start_date) {
            return false;
        }

        if ($this->end_date && $today > $this->end_date) {
            return false;
        }

        return true;
    }

    // Get applicable price list for an item
    public function getPriceFor(
        Item $item,
        ?string $variantCode = null,
        ?string $uom = null,
        float $quantity = 1,
        ?string $currency = null
    ): ?PricingMaster {
        return PricingMaster::getBestPrice(
            item: $item,
            pricingGroup: $this,
            variantCode: $variantCode,
            uom: $uom,
            quantity: $quantity,
            currency: $currency ?? $this->currency_code
        );
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }
}
