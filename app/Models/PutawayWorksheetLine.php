<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for Put-away Worksheet Lines
 */
class PutawayWorksheetLine extends Model
{
    protected $fillable = [
        'line_no',
        'putaway_worksheet_id',
        'warehouse_receipt_id',
        'item_id',
        'quantity',
        'qty_to_handle',
        'source_no',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'qty_to_handle' => 'decimal:4',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(PutawayWorksheet::class, 'putaway_worksheet_id');
    }

    public function warehouseReceipt(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceipt::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
