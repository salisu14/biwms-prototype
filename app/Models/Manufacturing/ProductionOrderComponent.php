<?php

namespace App\Models\Manufacturing;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderComponent extends Model
{
    use HasFactory;

    protected $table = 'production_order_components';

    protected $fillable = [
        'production_order_id',
        'line_number',
        'item_id',
        'description',
        'unit_of_measure_code',

        // Quantities
        'quantity_per',
        'expected_quantity',
        'expected_quantity_base',
        'actual_quantity_consumed',
        'actual_scrap_quantity',
        'remaining_quantity',

        // Scrap
        'scrap_percent',

        // Flushing
        'flushing_method',

        // Routing Link (when to consume)
        'routing_link_code',

        // Location
        'location_code',
        'bin_code',

        // Dates
        'due_date',

        // Reservation
        'reserved_quantity',

        // Cost
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity_per' => 'decimal:4',
        'expected_quantity' => 'decimal:4',
        'expected_quantity_base' => 'decimal:4',
        'actual_quantity_consumed' => 'decimal:4',
        'actual_scrap_quantity' => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'scrap_percent' => 'decimal:2',
        'reserved_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'due_date' => 'date',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getIsFullyConsumedAttribute(): bool
    {
        return $this->actual_quantity_consumed >= ($this->expected_quantity - 0.01);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return ($this->expected_quantity ?? 0) - ($this->actual_quantity_consumed ?? 0);
    }
}
