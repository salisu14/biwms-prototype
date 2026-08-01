<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionHierarchyReadinessClassification;
use App\Enums\ProductionHierarchyStatus;
use App\Models\Business;
use App\Models\User;
use Database\Factories\Manufacturing\ProductionHierarchyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class ProductionHierarchy extends Model
{
    /** @use HasFactory<ProductionHierarchyFactory> */
    use HasFactory;

    protected static bool $serviceMutationAllowed = false;

    protected $fillable = [
        'business_id',
        'root_production_order_id',
        'planning_version',
        'status',
        'readiness_classification',
        'max_depth',
        'node_count',
        'manufactured_component_count',
        'planned_quantity_base',
        'planned_uom_code',
        'supersedes_hierarchy_id',
        'superseded_by_hierarchy_id',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected $casts = [
        'status' => ProductionHierarchyStatus::class,
        'readiness_classification' => ProductionHierarchyReadinessClassification::class,
        'planning_version' => 'integer',
        'max_depth' => 'integer',
        'node_count' => 'integer',
        'manufactured_component_count' => 'integer',
        'planned_quantity_base' => 'decimal:8',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductionHierarchy $hierarchy): void {
            $hierarchy->assertBusinessScope();
        });

        static::updating(function (ProductionHierarchy $hierarchy): void {
            if (! static::$serviceMutationAllowed && $hierarchy->getOriginal('status') !== null) {
                $originalStatus = $hierarchy->getRawOriginal('status');
                $status = ProductionHierarchyStatus::tryFrom((string) $originalStatus);

                if ($status?->isImmutable()) {
                    throw new RuntimeException('Released or terminal production hierarchies cannot be changed directly.');
                }
            }
        });

        static::deleting(function (ProductionHierarchy $hierarchy): void {
            if (! static::$serviceMutationAllowed) {
                throw new RuntimeException('Production hierarchies are planning history and cannot be deleted directly.');
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
        foreach (['supersedesHierarchy', 'supersededByHierarchy'] as $relation) {
            $related = $this->{$relation};

            if ($this->business_id !== null && $related?->business_id !== null && (int) $related->business_id !== (int) $this->business_id) {
                throw new RuntimeException('Production hierarchy business scope does not match related hierarchy.');
            }
        }
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function rootProductionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'root_production_order_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(ProductionHierarchyNode::class);
    }

    public function supplyLinks(): HasMany
    {
        return $this->hasMany(ProductionOrderSupplyLink::class);
    }

    public function materialReservations(): HasMany
    {
        return $this->hasMany(ProductionMaterialReservation::class);
    }

    public function supersedesHierarchy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_hierarchy_id');
    }

    public function supersededByHierarchy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_hierarchy_id');
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
