<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionReservationType;
use App\Models\Business;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\User;
use Database\Factories\Manufacturing\ProductionMaterialReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ProductionMaterialReservation extends Model
{
    /** @use HasFactory<ProductionMaterialReservationFactory> */
    use HasFactory;

    protected static bool $serviceMutationAllowed = false;

    protected $fillable = [
        'business_id',
        'production_hierarchy_id',
        'production_hierarchy_node_id',
        'production_order_id',
        'production_order_component_id',
        'production_order_supply_link_id',
        'item_id',
        'location_id',
        'bin_code',
        'item_ledger_entry_id',
        'child_output_item_ledger_entry_id',
        'child_production_order_id',
        'reservation_type',
        'status',
        'quantity_base',
        'remaining_quantity_base',
        'reserved_until',
        'consumed_at',
        'released_at',
        'idempotency_key',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected $casts = [
        'reservation_type' => ProductionReservationType::class,
        'status' => ProductionReservationStatus::class,
        'quantity_base' => 'decimal:8',
        'remaining_quantity_base' => 'decimal:8',
        'reserved_until' => 'date',
        'consumed_at' => 'datetime',
        'released_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductionMaterialReservation $reservation): void {
            $reservation->assertBusinessScope();
        });

        static::updating(function (ProductionMaterialReservation $reservation): void {
            if (! static::$serviceMutationAllowed && $reservation->status?->isImmutable()) {
                throw new RuntimeException('Consumed, released, cancelled, or expired reservations cannot be changed directly.');
            }
        });

        static::deleting(function (ProductionMaterialReservation $reservation): void {
            if (! static::$serviceMutationAllowed) {
                throw new RuntimeException('Production material reservations cannot be deleted directly.');
            }
        });
    }

    public static function allowServiceMutation(callable $callback): mixed
    {
        static::$serviceMutationAllowed = true;

        try {
            return $callback();
        } finally {
            static::$serviceMutationAllowed = false;
        }
    }

    public function assertBusinessScope(): void
    {
        foreach (['productionHierarchy', 'productionHierarchyNode', 'productionOrderSupplyLink'] as $relation) {
            $related = $this->{$relation};

            if ($this->business_id !== null && $related?->business_id !== null && (int) $related->business_id !== (int) $this->business_id) {
                throw new RuntimeException('Production reservation business scope does not match related record.');
            }
        }
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function productionHierarchy(): BelongsTo
    {
        return $this->belongsTo(ProductionHierarchy::class);
    }

    public function productionHierarchyNode(): BelongsTo
    {
        return $this->belongsTo(ProductionHierarchyNode::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function productionOrderComponent(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderComponent::class);
    }

    public function productionOrderSupplyLink(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderSupplyLink::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function itemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class);
    }

    public function childOutputItemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class, 'child_output_item_ledger_entry_id');
    }

    public function childProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'child_production_order_id');
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
