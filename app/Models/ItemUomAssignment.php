<?php
// app/Models/ItemUomAssignment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUomAssignment extends Pivot
{
    use HasFactory;

    protected $table = 'item_uom_assignments';
    protected $primaryKey = 'assignment_id';

    public $incrementing = true;

    protected $fillable = [
        'item_id',
        'uom_id',
        'uom_type',
        'conversion_factor',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Get UOM type label
     */
    public function getUomTypeLabelAttribute(): string
    {
        return match($this->uom_type) {
            'BASE' => 'Base/Inventory',
            'SALES' => 'Sales',
            'PURCHASE' => 'Purchase',
            'SHIPPING' => 'Shipping',
            'REPORTING' => 'Reporting',
            'ALTERNATE' => 'Alternate',
            default => $this->uom_type,
        };
    }
}
