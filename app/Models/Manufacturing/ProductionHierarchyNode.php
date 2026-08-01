<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionBomLineBasis;
use App\Enums\ProductionHierarchyNodeStatus;
use App\Enums\ProductionHierarchyNodeType;
use App\Models\Business;
use App\Models\Item;
use Database\Factories\Manufacturing\ProductionHierarchyNodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class ProductionHierarchyNode extends Model
{
    /** @use HasFactory<ProductionHierarchyNodeFactory> */
    use HasFactory;

    protected static bool $serviceMutationAllowed = false;

    protected $fillable = [
        'business_id',
        'production_hierarchy_id',
        'root_production_order_id',
        'production_order_id',
        'parent_node_id',
        'node_path',
        'level',
        'node_type',
        'status',
        'item_id',
        'item_no',
        'description',
        'unit_of_measure_code',
        'required_quantity_base',
        'remaining_required_quantity_base',
        'planned_output_quantity_base',
        'reserved_quantity_base',
        'supplied_quantity_base',
        'source_bom_id',
        'source_bom_version_id',
        'source_bom_line_id',
        'source_production_order_component_id',
        'line_basis',
        'reference_quantity',
        'reference_uom_code',
        'reference_quantity_base',
        'idempotency_key',
        'snapshot',
        'metadata',
    ];

    protected $casts = [
        'node_type' => ProductionHierarchyNodeType::class,
        'status' => ProductionHierarchyNodeStatus::class,
        'line_basis' => ProductionBomLineBasis::class,
        'level' => 'integer',
        'required_quantity_base' => 'decimal:8',
        'remaining_required_quantity_base' => 'decimal:8',
        'planned_output_quantity_base' => 'decimal:8',
        'reserved_quantity_base' => 'decimal:8',
        'supplied_quantity_base' => 'decimal:8',
        'reference_quantity' => 'decimal:8',
        'reference_quantity_base' => 'decimal:8',
        'snapshot' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductionHierarchyNode $node): void {
            $node->assertBusinessScope();
        });

        static::updating(function (ProductionHierarchyNode $node): void {
            if (! static::$serviceMutationAllowed && $node->productionHierarchy?->status?->isImmutable()) {
                throw new RuntimeException('Nodes on released or terminal production hierarchies cannot be changed directly.');
            }
        });

        static::deleting(function (ProductionHierarchyNode $node): void {
            if (! static::$serviceMutationAllowed) {
                throw new RuntimeException('Production hierarchy nodes cannot be deleted directly.');
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
        foreach (['productionHierarchy', 'parentNode'] as $relation) {
            $related = $this->{$relation};

            if ($this->business_id !== null && $related?->business_id !== null && (int) $related->business_id !== (int) $this->business_id) {
                throw new RuntimeException('Production hierarchy node business scope does not match related record.');
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

    public function rootProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'root_production_order_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function parentNode(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_node_id');
    }

    public function childNodes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_node_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function sourceProductionOrderComponent(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderComponent::class, 'source_production_order_component_id');
    }

    public function supplyLinks(): HasMany
    {
        return $this->hasMany(ProductionOrderSupplyLink::class);
    }

    public function materialReservations(): HasMany
    {
        return $this->hasMany(ProductionMaterialReservation::class);
    }
}
