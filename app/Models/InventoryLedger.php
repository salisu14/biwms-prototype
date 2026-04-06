<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryLedger extends Model
{
    protected $fillable = [
        'item_id',
        'quantity',
        'cost_amount',
        'entry_type',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'cost_amount' => 'decimal:4',
    ];

    /**
     * Get the parent source model (e.g., Sale, Purchase).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the item this ledger entry refers to.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
