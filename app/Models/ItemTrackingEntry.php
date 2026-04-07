<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemTrackingEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'trackable_type',
        'trackable_id',
        'item_no',
        'variant_code',
        'serial_no',
        'lot_no',
        'expiration_date',
        'warranty_date',
        'quantity',
        'quantity_base',
        'entry_type',
        'document_type',
        'document_no',
        'document_line_no',
        'item_ledg_entry_no',
        'order_type',
        'order_no',
        'order_line_no',
        'correction',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'warranty_date' => 'date',
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'correction' => 'boolean',
    ];

    /**
     * Polymorphic relation to document line (SalesShipmentLine, etc.)
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_no', 'item_no');
    }

    /**
     * Scope for positive entries (inbound)
     */
    public function scopePositive($query)
    {
        return $query->where('entry_type', 'positive');
    }

    /**
     * Scope for negative entries (outbound)
     */
    public function scopeNegative($query)
    {
        return $query->where('entry_type', 'negative');
    }

    /**
     * Scope by lot number
     */
    public function scopeForLot($query, string $lotNo)
    {
        return $query->where('lot_no', $lotNo);
    }

    /**
     * Scope by serial number
     */
    public function scopeForSerial($query, string $serialNo)
    {
        return $query->where('serial_no', $serialNo);
    }

    /**
     * Get absolute quantity (always positive)
     */
    public function getAbsoluteQuantityAttribute(): float
    {
        return abs($this->quantity);
    }
}
