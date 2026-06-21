<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePrice extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vendor_id',
        'item_id',
        'starting_date',
        'ending_date',
        'minimum_quantity',
        'direct_unit_cost',
        'line_discount_percent',
        'unit_of_measure_code',
        'vendor_item_no',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'starting_date' => 'date',
        'ending_date' => 'date',
        'minimum_quantity' => 'decimal:4',
        'direct_unit_cost' => 'decimal:4',
        'line_discount_percent' => 'decimal:2',
    ];

    /**
     * Get the vendor that owns the purchase price.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the item associated with the purchase price.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Scope a query to only include prices active on a specific date.
     * Often used to find the "current" price.
     */
    public function scopeActiveOn(Builder $query, $date): Builder
    {
        return $query->where(function ($q) use ($date) {
            $q->whereNull('starting_date')
                ->orWhere('starting_date', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('ending_date')
                ->orWhere('ending_date', '>=', $date);
        });
    }

    /**
     * Scope a query to find the best price for a specific quantity.
     */
    public function scopeForQuantity(Builder $query, float $quantity): Builder
    {
        return $query->where('minimum_quantity', '<=', $quantity);
    }
}
