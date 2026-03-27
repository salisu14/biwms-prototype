<?php
// app/Models/ItemSku.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


#[Fillable([
    'item_id',
    'location_id',
    'sku_code',
    'barcode',              // NEW: Scannable barcode
    'reorder_point',
    'safety_stock',
    'lead_time_days',       // NEW: Lead time for reorder
    'is_active',
    'effective_date',       // NEW: When this SKU becomes active
    'expiry_date',          // NEW: When this SKU expires
])]
class ItemSku extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'item_skus';

    protected $casts = [
        'reorder_point' => 'decimal:4',
        'safety_stock' => 'decimal:4',
        'is_active' => 'boolean',
        'lead_time_days' => 'integer',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    /**
     * The item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class);
    }

    /**
     * The location
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationMaster::class);
    }

    /**
     * Auto-generate SKU code on create
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($sku) {
            if (empty($sku->sku_code)) {
                $sku->sku_code = sprintf(
                    '%s-%s',
                    $sku->item->item_code,
                    $sku->location->location_code
                );
            }
        });
    }

    /**
     * Current quantity at this SKU location
     */
    public function getCurrentQuantityAttribute(): float
    {
        return $this->item->quantityAtLocation($this->location_id);
    }

    /**
     * Check if below reorder point
     */
    public function getNeedsReorderAttribute(): bool
    {
        return $this->current_quantity <= $this->reorder_point;
    }

    /**
     * Check if SKU is currently effective
     */
    public function getIsEffectiveAttribute(): bool
    {
        $now = now();
        return $this->is_active
            && ($this->effective_date === null || $this->effective_date <= $now)
            && ($this->expiry_date === null || $this->expiry_date >= $now);
    }
}
