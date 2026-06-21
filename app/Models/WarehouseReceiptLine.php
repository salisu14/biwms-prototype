<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReceiptLine extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'warehouse_receipt_id',
        'line_number',
        'item_id',
        'variant_code',
        'description',
        'quantity',
        'quantity_received',
        'quantity_outstanding',
        'unit_of_measure_code',
        'qty_per_unit_of_measure',
        'zone_code',
        'bin_code',
        'serial_number',
        'lot_number',
        'expiration_date',
        'source_line_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'line_number' => 'integer',
        'quantity' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'quantity_outstanding' => 'decimal:4',
        'qty_per_unit_of_measure' => 'decimal:4',
        'expiration_date' => 'date',
    ];

    /**
     * Perform actions on model boot.
     */
    protected static function booted(): void
    {
        static::saving(function (WarehouseReceiptLine $line) {
            // Automatically calculate the outstanding quantity
            $line->quantity_outstanding = (float)$line->quantity - (float)$line->quantity_received;
        });
    }

    /**
     * Get the warehouse receipt that owns the line.
     */
    public function warehouseReceipt(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceipt::class);
    }

    /**
     * Get the item associated with the receipt line.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Calculate quantity in base unit of measure.
     */
    public function getBaseQuantityAttribute(): float
    {
        return (float)$this->quantity * (float)$this->qty_per_unit_of_measure;
    }

    /**
     * Check if the line is fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->quantity_outstanding <= 0;
    }
}
