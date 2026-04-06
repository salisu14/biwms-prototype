<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesQuoteItem extends Model
{
    protected $fillable = [
        'sales_quote_id',
        'item_id',
        'quantity',
        'unit_price',
        'discount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    /**
     * Automatic calculation logic for line totals and parent totals.
     */
    protected static function booted()
    {
        static::saving(function (SalesQuoteItem $item) {
            // Calculate line total: (Qty * Price) - Discount
            $item->line_total = ($item->quantity * $item->unit_price) - $item->discount;
        });

        static::saved(function (SalesQuoteItem $item) {
            $item->salesQuote->refreshTotal();
        });

        static::deleted(function (SalesQuoteItem $item) {
            $item->salesQuote->refreshTotal();
        });
    }

    public function salesQuote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class);
    }

    public function item(): BelongsTo
    {
        // Assuming your items table uses 'Item' model
        return $this->belongsTo(Item::class);
    }
}
