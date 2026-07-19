<?php

// app/Models/ItemUomAssignment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

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
        'conversion_factor' => 'decimal:12',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $assignment) {
            if (empty($assignment->uom_type)) {
                throw new \InvalidArgumentException(
                    'uom_type is required for ItemUomAssignment. '.
                    'Use base_uom_id on Item for the base unit, '.
                    'or explicitly set uom_type when creating assignments.'
                );
            }
        });

        static::saved(function (self $assignment) {
            if ($assignment->uom_type === 'BASE') {
                // Keep items.base_uom_id in sync with the BASE assignment
                Item::where('id', $assignment->item_id)
                    ->update(['base_uom_id' => $assignment->uom_id]);
            }
        });

        static::deleted(function (self $assignment) {
            if ($assignment->uom_type === 'BASE') {
                // Clear or recalculate if the BASE assignment is removed
                Item::where('id', $assignment->item_id)
                    ->update(['base_uom_id' => null]);
            }
        });
    }

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
        return match ($this->uom_type) {
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
