<?php
// app/Models/VendorItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'vendor_id',
    'item_id',
    'vendor_item_number',    // Vendor's SKU for this item
    'vendor_item_name',      // Vendor's description (may differ)
    'vendor_item_category',  // Vendor's classification
    'unit_cost',             // Vendor's price (in purchase UOM)
    'minimum_order_qty',     // MOQ
    'lead_time_days',        // Vendor-specific lead time
    'is_preferred',          // Is this the preferred vendor for this item?
    'is_active',             // Can we still order from this vendor?
    'effective_date',          // When this pricing becomes valid
    'expiry_date',           // When this pricing expires
    'last_purchase_date',
    'last_purchase_price',
    'currency',              // USD, EUR, etc.
    'price_breaks',            // JSON: {"100": 9.50, "500": 8.75, "1000": 8.00}
])]
class VendorItem extends Model
{
    use HasFactory;

    protected $table = 'vendor_items';

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'minimum_order_qty' => 'decimal:4',
        'lead_time_days' => 'integer',
        'is_preferred' => 'boolean',
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'last_purchase_date' => 'date',
        'last_purchase_price' => 'decimal:4',
        'price_breaks' => 'array',
    ];

    /**
     * The vendor
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * The item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Purchase history with this vendor
     */
    public function purchaseHistory(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'vendor_item_id');
    }

    /**
     * Get effective price based on quantity (with price breaks)
     */
    public function getPriceForQuantity(float $qty): float
    {
        if (empty($this->price_breaks)) {
            return (float) $this->unit_cost;
        }

        // Sort price breaks descending by quantity
        $breaks = collect($this->price_breaks)->sortKeysDesc();

        foreach ($breaks as $breakQty => $price) {
            if ($qty >= $breakQty) {
                return (float) $price;
            }
        }

        return (float) $this->unit_cost;
    }

    /**
     * Check if pricing is currently effective
     */
    public function getIsCurrentlyEffectiveAttribute(): bool
    {
        $now = now();
        return $this->is_active
            && ($this->effective_date === null || $this->effective_date <= $now)
            && ($this->expiry_date === null || $this->expiry_date >= $now);
    }

    /**
     * Scope: Active and effective
     */
    public function scopeActiveAndEffective($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_date')
                    ->orWhere('effective_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', $now);
            });
    }

    /**
     * Scope: Preferred vendors only
     */
    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    /**
     * Set as preferred (unset others for this item)
     */
    public function setAsPreferred(): void
    {
        // Unset other preferred vendors for this item
        self::where('item_id', $this->item_id)
            ->where('id', '!=', $this->id)
            ->update(['is_preferred' => false]);

        $this->is_preferred = true;
        $this->save();
    }
}
