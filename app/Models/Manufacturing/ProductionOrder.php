<?php

namespace App\Models\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderSourceType;
use App\Enums\ProductionOrderStatus;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WarehouseActivity;
use App\Models\WarehouseRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $table = 'production_orders';

    protected $fillable = [
        'document_number',
        'status',
        'source_type',
        'source_id', // Item ID, Family ID, or Sales Order ID
        'source_no',
        'description',

        // Item Information
        'item_id',
        'variant_code',
        'quantity',
        'unit_of_measure_code',
        'quantity_base',

        // Dates
        'due_date',
        'starting_date_time',
        'ending_date_time',

        // Posting Groups (from WMS Posting Groups Setup)
        'inventory_posting_group_id',
        'general_business_posting_group_id',
        'general_product_posting_group_id',

        // BOM and Routing
        'production_bom_id',
        'routing_id',
        'production_bom_version_id',
        'routing_version_id',

        // Location/Warehouse
        'location_code',
        'bin_code',

        // Dimension Codes
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'capex_project_id',

        // Costing
        'costing_method', // STANDARD, FIFO, LIFO, AVERAGE, SPECIFIC
        'unit_cost',
        'cost_rollup',

        // Flushing Method (from item/routing)
        'flushing_method', // MANUAL, FORWARD, BACKWARD, PICK + BACKWARD, PICK + FORWARD

        // Scrap
        'scrap_percent',

        // Planning
        'planning_level',
        'priority',

        // Posted Status
        'posted',
        'posted_at',
        'posted_by',

        // Finished Status
        'finished_at',
        'finished_by',

        // User Tracking
        'created_by',
        'last_modified_by',

        // Reservation
        'reserved_from_stock',
    ];

    protected $casts = [
        'status' => ProductionOrderStatus::class,
        'source_type' => ProductionOrderSourceType::class,
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'due_date' => 'date',
        'starting_date_time' => 'datetime',
        'ending_date_time' => 'datetime',
        'unit_cost' => 'decimal:4',
        'cost_rollup' => 'decimal:4',
        'scrap_percent' => 'decimal:2',
        'posted' => 'boolean',
        'posted_at' => 'datetime',
        'finished_at' => 'datetime',
        'reserved_from_stock' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================
    // Add to existing ProductionOrder model

    public function warehouseActivities(): HasMany|ProductionOrder
    {
        return $this->hasMany(WarehouseActivity::class, 'source_no', 'order_no')
            ->where('source_document', 'production_order');
    }

    public function warehouseRequests(): HasMany|ProductionOrder
    {
        return $this->hasMany(WarehouseRequest::class, 'source_no', 'order_no')
            ->where('source_document', 'production_order');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_code', 'uom_code');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function productionBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id');
    }

    // ProductionOrder.php
    public function getPostingSetup(): ?GeneralPostingSetup
    {
        return GeneralPostingSetup::where('general_business_posting_group_id', $this->general_business_posting_group_id)
            ->where('general_product_posting_group_id', $this->general_product_posting_group_id)
            ->first();
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function capexProject(): BelongsTo
    {
        return $this->belongsTo(CapExProject::class, 'capex_project_id');
    }

    public function routingVersion(): BelongsTo
    {
        return $this->belongsTo(RoutingVersion::class, 'routing_version_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionOrderLine::class, 'production_order_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductionOrderComponent::class, 'production_order_id');
    }

    public function routingLines(): HasMany
    {
        return $this->hasMany(ProductionOrderRoutingLine::class, 'production_order_id');
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class, 'inventory_posting_group_id');
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class, 'general_product_posting_group_id');
    }

    //    public function warehouseRequests(): MorphMany
    //    {
    //        return $this->morphMany(WarehouseRequest::class, 'source', 'source_document', 'source_id');
    //    }
    //
    //    public function warehouseActivities(): MorphMany
    //    {
    //        return $this->morphMany(WarehouseActivity::class, 'source', 'source_document', 'source_id');
    //    }

    public function capacityLedgerEntries(): HasMany
    {
        return $this->hasMany(CapacityLedgerEntry::class, 'production_order_id');
    }

    public function itemLedgerEntries(): MorphMany
    {
        return $this->morphMany(ItemLedgerEntry::class, 'source', 'source_type', 'source_id');
    }

    public function glEntries(): MorphMany
    {
        return $this->morphMany(GlEntry::class, 'sourceable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by');
    }

    // ==================== SCOPES ====================

    public function scopeForStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSimulated($query)
    {
        return $query->where('status', ProductionOrderStatus::SIMULATED);
    }

    public function scopePlanned($query)
    {
        return $query->where('status', ProductionOrderStatus::PLANNED);
    }

    public function scopeFirmPlanned($query)
    {
        return $query->where('status', ProductionOrderStatus::FIRM_PLANNED);
    }

    public function scopeReleased($query)
    {
        return $query->where('status', ProductionOrderStatus::RELEASED);
    }

    public function scopeFinished($query)
    {
        return $query->where('status', ProductionOrderStatus::FINISHED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            ProductionOrderStatus::FIRM_PLANNED,
            ProductionOrderStatus::RELEASED,
        ]);
    }

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeDueBefore($query, $date)
    {
        return $query->where('due_date', '<=', $date);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getRemainingQuantityAttribute(): float
    {
        $produced = $this->itemLedgerEntries()
            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->sum('quantity');

        return $this->quantity - $produced;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->remaining_quantity <= 0;
    }

    public function getTotalActualCostAttribute(): float
    {
        return $this->capacityLedgerEntries()->sum('total_cost') +
            $this->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
                ->sum('cost_amount_actual');
    }

    public function getCostVarianceAttribute(): float
    {
        if (! $this->cost_rollup) {
            return 0;
        }

        return $this->total_actual_cost - ($this->cost_rollup * $this->quantity);
    }

    // ==================== BOOTED ====================

    protected static function booted(): void
    {
        static::creating(function ($order) {
            $order->created_by = auth()->id();
        });

        static::updating(function ($order) {
            $order->last_modified_by = auth()->id();
        });
    }
}
