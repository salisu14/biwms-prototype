<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemTrackingLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_type',
        'source_id',
        'source_ref_no',
        'item_no',
        'variant_code',
        'location_code',
        'serial_no',
        'lot_no',
        'expiration_date',
        'warranty_date',
        'quantity',
        'quantity_base',
        'quantity_to_handle',
        'quantity_to_invoice',
        'quantity_handled',
        'quantity_invoiced',
        'appl_to_item_entry',
        'correction',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'warranty_date' => 'date',
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'quantity_to_handle' => 'decimal:4',
        'quantity_to_invoice' => 'decimal:4',
        'quantity_handled' => 'decimal:4',
        'quantity_invoiced' => 'decimal:4',
        'correction' => 'boolean',
    ];

    /**
     * Scope by source document
     */
    public function scopeForSource($query, string $type, int $id)
    {
        return $query->where('source_type', $type)->where('source_id', $id);
    }

    /**
     * Scope for specific item
     */
    public function scopeForItem($query, string $itemNo)
    {
        return $query->where('item_no', $itemNo);
    }

    /**
     * Scope for lot-tracked items
     */
    public function scopeWithLot($query)
    {
        return $query->whereNotNull('lot_no');
    }

    /**
     * Scope for serial-tracked items
     */
    public function scopeWithSerial($query)
    {
        return $query->whereNotNull('serial_no');
    }

    /**
     * Check if line is fully handled
     */
    public function isFullyHandled(): bool
    {
        return $this->quantity_handled >= $this->quantity;
    }

    /**
     * Check if line is fully invoiced
     */
    public function isFullyInvoiced(): bool
    {
        return $this->quantity_invoiced >= $this->quantity;
    }

    /**
     * Get remaining quantity to handle
     */
    public function getRemainingToHandleAttribute(): float
    {
        return max(0, $this->quantity - $this->quantity_handled);
    }

    /**
     * Get remaining quantity to invoice
     */
    public function getRemainingToInvoiceAttribute(): float
    {
        return max(0, $this->quantity - $this->quantity_invoiced);
    }
}
