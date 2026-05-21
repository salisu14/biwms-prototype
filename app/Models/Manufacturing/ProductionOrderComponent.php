<?php

namespace App\Models\Manufacturing;

use App\Enums\FlushingMethod;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\WarehouseRequest;
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

        // Explosion traceability
        'bom_level',
        'bom_path',
        'source_bom_code',
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
        'bom_level' => 'integer',
    ];

    public function warehouseRequests()
    {
        return $this->morphMany(WarehouseRequest::class, 'source', 'source_document', 'source_id')
            ->where('source_document', 'production_order_component');
    }

    public function getAvailableRemainingQuantityAttribute(): float
    {
        $requested = (float) $this->warehouseRequests()
            ->whereIn('status', ['open', 'partial'])
            ->sum('quantity_outstanding');

        return max(0, (float) ($this->remaining_quantity ?? 0) - $requested);
    }

    public function flushingMethod(): FlushingMethod
    {
        // Check work center flushing method
        $workCenter = $this->routingLine?->workCenter;
        if ($workCenter && $workCenter->workCenterBin) {
            return $workCenter->workCenterBin->flushing_method;
        }

        $configuredFlushingMethod = strtolower((string) $this->flushing_method);

        return FlushingMethod::tryFrom($configuredFlushingMethod) ?? FlushingMethod::MANUAL;
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_code', 'uom_code');
    }

    public function routingLine()
    {
        // Custom relationship because it depends on two keys
        return $this->hasOne(ProductionOrderRoutingLine::class, 'routing_link_code', 'routing_link_code')
            ->where('production_order_id', $this->production_order_id);
    }

    public function getIsFullyConsumedAttribute(): bool
    {
        return (float) $this->remaining_quantity <= 0.01;
    }
}
