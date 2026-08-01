<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionSupplyLinkStatus;
use App\Enums\ProductionSupplyType;
use App\Models\Business;
use App\Models\Item;
use App\Models\User;
use Database\Factories\Manufacturing\ProductionOrderSupplyLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class ProductionOrderSupplyLink extends Model
{
    /** @use HasFactory<ProductionOrderSupplyLinkFactory> */
    use HasFactory;

    protected static bool $serviceMutationAllowed = false;

    protected $fillable = [
        'business_id',
        'production_hierarchy_id',
        'production_hierarchy_node_id',
        'root_production_order_id',
        'parent_production_order_id',
        'parent_component_id',
        'child_production_order_id',
        'item_id',
        'unit_of_measure_code',
        'supply_type',
        'status',
        'required_quantity_base',
        'planned_supply_quantity_base',
        'produced_quantity_base',
        'supplied_quantity_base',
        'consumed_quantity_base',
        'idempotency_key',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected $casts = [
        'supply_type' => ProductionSupplyType::class,
        'status' => ProductionSupplyLinkStatus::class,
        'required_quantity_base' => 'decimal:8',
        'planned_supply_quantity_base' => 'decimal:8',
        'produced_quantity_base' => 'decimal:8',
        'supplied_quantity_base' => 'decimal:8',
        'consumed_quantity_base' => 'decimal:8',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductionOrderSupplyLink $link): void {
            $link->assertBusinessScope();
        });

        static::deleting(function (ProductionOrderSupplyLink $link): void {
            if (static::$serviceMutationAllowed) {
                return;
            }

            if (
                ((float) $link->produced_quantity_base > 0)
                || ((float) $link->supplied_quantity_base > 0)
                || ((float) $link->consumed_quantity_base > 0)
                || $link->materialReservations()->exists()
            ) {
                throw new RuntimeException('Production supply links with activity or reservations cannot be deleted directly.');
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
        foreach (['productionHierarchy', 'productionHierarchyNode'] as $relation) {
            $related = $this->{$relation};

            if ($this->business_id !== null && $related?->business_id !== null && (int) $related->business_id !== (int) $this->business_id) {
                throw new RuntimeException('Production supply link business scope does not match related record.');
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

    public function rootProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'root_production_order_id');
    }

    public function parentProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'parent_production_order_id');
    }

    public function parentComponent(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderComponent::class, 'parent_component_id');
    }

    public function childProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'child_production_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function materialReservations(): HasMany
    {
        return $this->hasMany(ProductionMaterialReservation::class);
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
