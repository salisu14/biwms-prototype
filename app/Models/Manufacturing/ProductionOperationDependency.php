<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionOperationDependencyStatus;
use App\Enums\ProductionOperationDependencyType;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOperationDependency extends Model
{
    protected $fillable = [
        'business_id',
        'production_hierarchy_id',
        'root_production_order_id',
        'upstream_production_order_id',
        'upstream_routing_line_id',
        'downstream_production_order_id',
        'downstream_routing_line_id',
        'production_order_supply_link_id',
        'item_id',
        'dependency_type',
        'status',
        'required_quantity_base',
        'minimum_start_quantity_base',
        'fulfilled_quantity_base',
        'sequence',
        'source',
        'idempotency_key',
        'last_evaluated_at',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dependency_type' => ProductionOperationDependencyType::class,
        'status' => ProductionOperationDependencyStatus::class,
        'required_quantity_base' => 'decimal:8',
        'minimum_start_quantity_base' => 'decimal:8',
        'fulfilled_quantity_base' => 'decimal:8',
        'last_evaluated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function productionHierarchy(): BelongsTo
    {
        return $this->belongsTo(ProductionHierarchy::class);
    }

    public function rootProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'root_production_order_id');
    }

    public function upstreamProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'upstream_production_order_id');
    }

    public function upstreamRoutingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'upstream_routing_line_id');
    }

    public function downstreamProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'downstream_production_order_id');
    }

    public function downstreamRoutingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'downstream_routing_line_id');
    }

    public function supplyLink(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderSupplyLink::class, 'production_order_supply_link_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function handoffs(): HasMany
    {
        return $this->hasMany(ProductionIntermediateHandoff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
