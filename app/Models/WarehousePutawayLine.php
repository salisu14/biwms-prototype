<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * Model for Warehouse Put-away Lines (Advanced - Take/Place)
 */
class WarehousePutawayLine extends Model
{
    protected $fillable = [
        'warehouse_putaway_id',
        'line_no',
        'action_type', // Take or Place
        'item_id',
        'bin_id',
        'zone_id',
        'quantity',
        'qty_to_handle',
        'qty_handled',
        'unit_of_measure',
        'source_document',
        'source_no',
        'source_line_no',
        'breakbulk',
        'item_tracking',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'qty_to_handle' => 'decimal:4',
        'qty_handled' => 'decimal:4',
        'breakbulk' => 'boolean',
        'item_tracking' => 'json',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(WarehousePutaway::class, 'warehouse_putaway_id');
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
