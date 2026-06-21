<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for Inventory Put-away Lines (Basic)
 */
class InventoryPutawayLine extends Model
{
    protected $fillable = [
        'inventory_putaway_id',
        'line_no',
        'item_id',
        'bin_id',
        'quantity',
        'qty_to_handle',
        'qty_handled',
        'unit_of_measure',
        'item_tracking',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'qty_to_handle' => 'decimal:4',
        'qty_handled' => 'decimal:4',
        'item_tracking' => 'json', // Cast to JSON for lot/serial data
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(InventoryPutaway::class, 'inventory_putaway_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }
}
