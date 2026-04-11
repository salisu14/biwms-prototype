<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseShipmentLine extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'warehouse_shipment_id',
        'line_number',
        'item_id',
        'variant_code',
        'description',
        'quantity',
        'quantity_shipped',
        'quantity_outstanding',
        'quantity_picked',
        'unit_of_measure_code',
        'qty_per_unit_of_measure',
        'zone_code',
        'bin_code',
        'serial_number',
        'lot_number',
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
        'quantity_shipped' => 'decimal:4',
        'quantity_outstanding' => 'decimal:4',
        'quantity_picked' => 'decimal:4',
        'qty_per_unit_of_measure' => 'decimal:4',
    ];

    /**
     * Perform actions on model boot.
     */
    protected static function booted(): void
    {
        static::saving(function (WarehouseShipmentLine $line) {
            // Automatically calculate the outstanding quantity for shipping
            $line->quantity_outstanding = (float)$line->quantity - (float)$line->quantity_shipped;
        });
    }

    /**
     * Get the warehouse shipment that owns the line.
     */
    public function warehouseShipment(): BelongsTo
    {
        return $this->belongsTo(WarehouseShipment::class);
    }

    /**
     * Get the item associated with the shipment line.
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
     * Check if the line is fully shipped.
     */
    public function isFullyShipped(): bool
    {
        return $this->quantity_outstanding <= 0;
    }

    /**
     * Check if the line is fully picked and ready for shipping.
     */
    public function isFullyPicked(): bool
    {
        return (float)$this->quantity_picked >= (float)$this->quantity;
    }
}
