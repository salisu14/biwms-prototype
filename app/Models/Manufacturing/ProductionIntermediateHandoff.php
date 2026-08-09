<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionIntermediateHandoffStatus;
use App\Models\Business;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionIntermediateHandoff extends Model
{
    protected $fillable = [
        'business_id',
        'production_hierarchy_id',
        'production_operation_dependency_id',
        'production_order_supply_link_id',
        'production_material_reservation_id',
        'source_production_order_id',
        'source_routing_line_id',
        'destination_production_order_id',
        'destination_routing_line_id',
        'item_id',
        'child_output_item_ledger_entry_id',
        'lot_number',
        'serial_number',
        'quantity_required_base',
        'quantity_available_base',
        'quantity_transferred_base',
        'status',
        'quality_status',
        'idempotency_key',
        'last_synced_at',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity_required_base' => 'decimal:8',
        'quantity_available_base' => 'decimal:8',
        'quantity_transferred_base' => 'decimal:8',
        'status' => ProductionIntermediateHandoffStatus::class,
        'last_synced_at' => 'datetime',
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

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationDependency::class, 'production_operation_dependency_id');
    }

    public function supplyLink(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderSupplyLink::class, 'production_order_supply_link_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ProductionMaterialReservation::class, 'production_material_reservation_id');
    }

    public function sourceProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'source_production_order_id');
    }

    public function sourceRoutingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'source_routing_line_id');
    }

    public function destinationProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'destination_production_order_id');
    }

    public function destinationRoutingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'destination_routing_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function childOutputItemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class, 'child_output_item_ledger_entry_id');
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
