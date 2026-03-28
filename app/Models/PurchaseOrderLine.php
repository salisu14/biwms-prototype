<?php
// app/Models/PurchaseOrderLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'line_number',
    'item_id',
    'item_code',
    'description',
    'quantity',
    'unit_of_measure',
    'unit_cost',
    'line_total',
    'vat_code',
    'vat_percentage',
    'vat_amount',
    'total_amount',
    'received_quantity',
    'returned_quantity',
    'invoiced_quantity',
    'expected_delivery_date',
    'comment'
])]
class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'purchase_order_lines';

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'line_total' => 'decimal:4',
        'vat_percentage' => 'decimal:2',
        'vat_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'returned_quantity' => 'decimal:4',
        'invoiced_quantity' => 'decimal:4',
        'expected_delivery_date' => 'date',
    ];

    /**
     * Parent order
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Item master
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class);
    }

    /**
     * Calculate line totals before save
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($line) {
            $line->line_total = $line->quantity * $line->unit_cost;
            $line->vat_amount = $line->line_total * ($line->vat_percentage / 100);
            $line->total_amount = $line->line_total + $line->vat_amount;
        });
    }

    /**
     * Get remaining quantity to receive
     */
    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    /**
     * Check if fully received
     */
    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->received_quantity >= $this->quantity;
    }

    /**
     * Check if partially received
     */
    public function getIsPartiallyReceivedAttribute(): bool
    {
        return $this->received_quantity > 0 && $this->received_quantity < $this->quantity;
    }

    // In PurchaseOrderLine model
    public function getLineTotalAttribute(): float
    {
        return $this->quantity * $this->unit_cost;
    }

    public function getVatAmountAttribute(): float
    {
        return $this->line_total * ($this->vat_percentage / 100);
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->line_total + $this->vat_amount;
    }
}
