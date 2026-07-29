<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomVersion;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\Routing;
use App\Models\Manufacturing\RoutingVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionExpectedCostSnapshot extends Model
{
    protected $fillable = [
        'production_order_id',
        'finished_item_id',
        'production_bom_id',
        'production_bom_version_id',
        'routing_id',
        'routing_version_id',
        'production_quantity_base',
        'costing_date',
        'expected_material_cost',
        'expected_capacity_cost',
        'expected_overhead_cost',
        'expected_output_cost',
        'expected_total_cost',
        'calculation_identity',
        'status',
        'component_details',
        'routing_details',
        'cost_source_details',
        'metadata',
        'calculated_by',
        'calculated_at',
    ];

    protected $casts = [
        'production_quantity_base' => 'decimal:8',
        'costing_date' => 'date',
        'expected_material_cost' => 'decimal:4',
        'expected_capacity_cost' => 'decimal:4',
        'expected_overhead_cost' => 'decimal:4',
        'expected_output_cost' => 'decimal:4',
        'expected_total_cost' => 'decimal:4',
        'component_details' => 'array',
        'routing_details' => 'array',
        'cost_source_details' => 'array',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function finishedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'finished_item_id');
    }

    public function productionBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class);
    }

    public function productionBomVersion(): BelongsTo
    {
        return $this->belongsTo(ProductionBomVersion::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function routingVersion(): BelongsTo
    {
        return $this->belongsTo(RoutingVersion::class);
    }

    public function valueEntries(): HasMany
    {
        return $this->hasMany(ValueEntry::class, 'source_id')
            ->where('source_type', 'PRODUCTION_EXPECTED_COST');
    }
}
