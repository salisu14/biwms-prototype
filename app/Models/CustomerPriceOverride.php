<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class CustomerPriceOverride
 * * This model manages custom pricing rules for specific customers on specific items.
 * When a quote or invoice is generated, the system should check this table
 * before falling back to the standard item price.
 */
class CustomerPriceOverride extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'item_id',
        'override_price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'override_price' => 'decimal:2',
        'customer_id' => 'integer',
        'item_id' => 'integer',
    ];

    /**
     * Get the customer associated with this price override.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the item associated with this price override.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Scope a query to find a specific override for a customer and item.
     * * Usage: CustomerPriceOverride::forCustomerAndItem($cId, $iId)->first();
     */
    public function scopeForCustomerAndItem(Builder $query, int $customerId, int $itemId): Builder
    {
        return $query->where('customer_id', $customerId)
            ->where('item_id', $itemId);
    }

    /**
     * Helper to get the price directly.
     */
    public static function getPriceFor(int $customerId, int $itemId): ?float
    {
        $override = self::forCustomerAndItem($customerId, $itemId)->first();

        return $override ? (float) $override->override_price : null;
    }
}
