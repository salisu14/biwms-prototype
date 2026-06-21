<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_no',
        'item_no',
        'variant_code',
        'location_code',
        'serial_no',
        'lot_no',
        'quantity',
        'quantity_base',
        'reservation_status',
        'source_type',
        'source_id',
        'source_ref_no',
        'source_subtype',
        'binding_entry_no',
        'expected_receipt_date',
        'shipment_date',
        'expiration_date',
        'warranty_date',
        'qty_to_handle',
        'qty_to_invoice',
        'correction',
        'item_ledg_entry_no',
        'planning_level',
        'planning_line_no',
        'reservation_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'qty_to_handle' => 'decimal:4',
        'qty_to_invoice' => 'decimal:4',
        'expected_receipt_date' => 'date',
        'shipment_date' => 'date',
        'expiration_date' => 'date',
        'warranty_date' => 'date',
        'correction' => 'boolean',
        'planning_level' => 'boolean',
    ];

    /**
     * Boot method to auto-generate entry number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->entry_no)) {
                $model->entry_no = static::max('entry_no') + 1;
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_no', 'item_no');
    }

    /**
     * Scope for active reservations
     */
    public function scopeReservation($query)
    {
        return $query->where('reservation_status', 'reservation');
    }

    /**
     * Scope for tracking entries
     */
    public function scopeTracking($query)
    {
        return $query->where('reservation_status', 'tracking');
    }

    /**
     * Scope for surplus entries
     */
    public function scopeSurplus($query)
    {
        return $query->where('reservation_status', 'surplus');
    }

    /**
     * Scope by source document
     */
    public function scopeForSource($query, string $type, int $id)
    {
        return $query->where('source_type', $type)->where('source_id', $id);
    }

    /**
     * Scope by item and location
     */
    public function scopeForItemLocation($query, string $itemNo, string $locationCode)
    {
        return $query->where('item_no', $itemNo)->where('location_code', $locationCode);
    }

    /**
     * Scope for available inventory (positive quantity)
     */
    public function scopePositive($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope for demand (negative quantity)
     */
    public function scopeNegative($query)
    {
        return $query->where('quantity', '<', 0);
    }

    /**
     * Check if reservation is bound to another entry
     */
    public function isBound(): bool
    {
        return $this->binding_entry_no !== null;
    }

    /**
     * Get the binding entry
     */
    public function bindingEntry(): ?self
    {
        return $this->binding_entry_no ? static::find($this->binding_entry_no) : null;
    }
}
