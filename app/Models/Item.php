<?php

namespace App\Models;

use App\Enums\CostingMethod;
use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\UomType;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\Routing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'description',
        'description_2',
        'general_product_posting_group_id',
        'inventory_posting_group_id',
        'vat_product_posting_group_id',
        'currency_id',
        'sku',
        'item_category_id',
        'inventory_bin_id',
        'vat_id',
        'item_type',
        'production_bom_id',
        'routing_id',
        'inventory_method',
        'costing_method',
        'unit_cost',
        'standard_cost',
        'last_direct_cost',
        'unit_price',
        'profit_percent',
        'price_calculation_method',
        'default_price_list_code',
        'allow_negative_price',
        'inventory',
        'reorder_point',
        'reorder_quantity',
        'location_id',
        'bin_code',
        'weight',
        'volume',
        'shelf_no',
        'item_tracking_code',
        'shelf_life_days',
        'is_active',
        'blocked',
        'sales_blocked',
        'purchasing_blocked',
        'base_uom_id',
    ];

    protected $casts = [
        'item_type' => ItemType::class,
        'inventory_method' => InventoryMethod::class,
        'costing_method' => CostingMethod::class,
        'unit_cost' => 'decimal:4',
        'standard_cost' => 'decimal:4',
        'last_direct_cost' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'profit_percent' => 'decimal:2',
        'inventory' => 'decimal:4',
        'reorder_point' => 'decimal:4',
        'reorder_quantity' => 'decimal:4',
        'weight' => 'decimal:4',
        'volume' => 'decimal:4',
        'shelf_life_days' => 'integer',
        'is_active' => 'boolean',
        'blocked' => 'boolean',
        'sales_blocked' => 'boolean',
        'purchasing_blocked' => 'boolean',
        'allow_negative_price' => 'boolean',
    ];

    /**
     * UOM Assignments
     */
    public function uomAssignments(): HasMany
    {
        return $this->hasMany(ItemUomAssignment::class);
    }

    // In Item model, add:
    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    /**
     * Additional UOM assignments (excluding base UOM)
     * Use uomAssignments() HasMany for full control, or this for convenience
     */
    public function uoms(): BelongsToMany
    {
        return $this->belongsToMany(
            UnitOfMeasure::class,
            'item_uom_assignments',
            'item_id',
            'uom_id'
        )
            ->using(ItemUomAssignment::class)
            ->withPivot(['uom_type', 'conversion_factor', 'is_default'])
            ->withTimestamps();
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'item_category_id');
    }

    public function categoryAssignments(): HasMany
    {
        return $this->hasMany(ItemCategoryAssignment::class);
    }

    public function unitOfMeasures(): BelongsToMany
    {
        return $this->belongsToMany(
            UnitOfMeasure::class,
            'item_uom_assignments',
            'item_id',          // FK to items
            'uom_id'            // 🔥 CHANGE THIS to your actual column name
        )
            ->withPivot([
                'uom_type',
                'conversion_factor',
                'is_default',
            ])
            ->withTimestamps();
    }

    public function getUomByType(UomType|string $type): ?UnitOfMeasure
    {
        $typeValue = is_string($type) ? $type : $type->value;

        return $this->uoms()
            ->wherePivot('uom_type', $typeValue)
            ->wherePivot('is_default', true)
            ->first();
    }

    public function getDefaultUom(UomType|string $type): ?UnitOfMeasure
    {
        $typeValue = is_string($type) ? $type : $type->value;

        return $this->getUomByType($type)
            ?? $this->uoms()->wherePivot('uom_type', $typeValue)->first();
    }

    public function getConversionFactorForUom(?string $uomCode): float
    {
        if (! $uomCode) {
            return 1.0;
        }

        $uom = $this->uoms()
            ->where('uom_code', $uomCode)
            ->first();

        if ($uom?->pivot?->conversion_factor) {
            return (float) $uom->pivot->conversion_factor;
        }

        return 1.0;
    }

    /**
     * Relationships
     */
    public function productionBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function vat(): BelongsTo
    {
        return $this->belongsTo(VatMaster::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function inventoryBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'inventory_bin_id');
    }

    public function itemTrackingCodeDefinition(): BelongsTo
    {
        return $this->belongsTo(ItemTrackingCode::class, 'item_tracking_code', 'code');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ItemSku::class);
    }

    /**
     * Safe business logic
     */
    public function getInventoryAccount(?int $locationId = null): ?ChartOfAccount
    {
        return $this->inventoryPostingGroup?->getInventoryAccount($locationId);
    }

    public function isInventoryItem(): bool
    {
        return $this->item_type === ItemType::INVENTORY;
    }

    public function isServiceItem(): bool
    {
        return $this->item_type === ItemType::SERVICE;
    }

    public function inventoryValue(): float
    {
        return (float) $this->inventory * (float) $this->unit_cost;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeAvailableForSale($query)
    {
        return $query->where('blocked', false)
            ->where('sales_blocked', false)
            ->where('item_type', '!=', ItemType::SERVICE);
    }

    public function scopeAvailableForPurchase($query)
    {
        return $query->where('blocked', false)
            ->where('purchasing_blocked', false);
    }

    public function scopeOfType($query, ItemType $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Inventory tracking
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedgerEntry::class);
    }

    public function salesOrderLines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function getTotalQuantityAttribute(): float
    {
        return (float) $this->ledgerEntries()->sum('quantity');
    }

    public function getLedgerOnHandAttribute(): float
    {
        $ledgerOnHand = (float) $this->ledgerEntries()
            ->where('open', true)
            ->sum('remaining_quantity');

        if ($ledgerOnHand > 0) {
            return $ledgerOnHand;
        }

        // Backward-compatible fallback for environments that still keep
        // opening stock only on the item card (without open ledger layers).
        return (float) ($this->inventory ?? 0);
    }

    public function getBaseUnitOfMeasureAttribute(): string
    {
        return $this->baseUom?->uom_code ?? 'PCS';
    }

    public function getQtyOnSalesOrderAttribute(): float
    {
        $openStatuses = [
            SalesOrderStatus::DRAFT->value,
            SalesOrderStatus::PENDING_APPROVAL->value,
            SalesOrderStatus::APPROVED->value,
            SalesOrderStatus::RELEASED->value,
            SalesOrderStatus::PICKING->value,
            SalesOrderStatus::PACKED->value,
            SalesOrderStatus::PARTIALLY_INVOICED->value,
        ];

        return (float) $this->salesOrderLines()
            ->whereHas('salesOrder', fn ($query) => $query->whereIn('status', $openStatuses))
            ->get()
            ->sum(function (SalesOrderLine $line): float {
                $remainingSalesUom = max(0, (float) $line->quantity - (float) $line->quantity_shipped);
                $qtyPerUom = (float) ($line->qty_per_unit_of_measure ?: 1.0);

                return $remainingSalesUom * $qtyPerUom;
            });
    }

    public function getQtyOnPurchaseOrderAttribute(): float
    {
        $openStatuses = [
            PurchaseOrderStatus::PENDING->value,
            PurchaseOrderStatus::APPROVED->value,
            PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
        ];

        return (float) $this->purchaseOrderLines()
            ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', $openStatuses))
            ->get()
            ->sum(fn (PurchaseOrderLine $line): float => max(0, (float) $line->quantity - (float) $line->received_quantity));
    }

    public function getAvailableToPromiseAttribute(): float
    {
        return (float) $this->ledger_on_hand - (float) $this->qty_on_sales_order;
    }

    public function getProjectedAvailableAttribute(): float
    {
        return (float) $this->available_to_promise + (float) $this->qty_on_purchase_order;
    }

    public function getNeedsReorderAttribute(): bool
    {
        $reorderPoint = (float) ($this->reorder_point ?? 0);

        if ($reorderPoint <= 0) {
            return false;
        }

        return (float) $this->projected_available <= $reorderPoint;
    }

    public function quantityAtLocation(int $locationId): float
    {
        return (float) $this->ledgerEntries()
            ->where('location_id', $locationId)
            ->sum('quantity');
    }

    /**
     * Vendor relations
     */
    public function vendorItems(): HasMany
    {
        return $this->hasMany(VendorItem::class);
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'vendor_items')
            ->withPivot('vendor_item_number', 'vendor_item_name', 'is_preferred');
    }

    public function getPreferredVendor(): ?Vendor
    {
        return $this->vendors()
            ->wherePivot('is_preferred', true)
            ->first();
    }

    /**
     * Categories
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'item_category_assignments')
            ->withPivot('is_primary', 'sort_order')
            ->orderByPivot('sort_order');
    }

    public function getPrimaryCategory(): ?Category
    {
        return $this->categories()
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * UOM grouping (FIXED)
     */
    public function getUomsByTypeAttribute(): array
    {
        return $this->uomAssignments()
            ->with('uom')
            ->get()
            ->groupBy('uom_type')
            ->map(fn ($group) => $group->map(fn ($a) => [
                'uom_id' => $a->uom_id,
                'uom_code' => $a->uom->uom_code,
                'description' => $a->uom->description,
                'conversion_factor' => (float) $a->conversion_factor,
                'is_default' => $a->is_default,
            ]))
            ->toArray();
    }

    /**
     * Sync to SKUs
     */
    public function syncToSkus(?array $fields = null): int
    {
        $defaultFields = [
            'item_category_id',
            'inventory_posting_group_id',
            'vat_product_posting_group_id',
            'purchasing_blocked',
            'sales_blocked',
        ];

        $fields = $fields ?? $defaultFields;

        $data = collect($this->getAttributes())
            ->only($fields)
            ->toArray();

        return $this->skus()->update($data);
    }

    public function scopeRawMaterials($query)
    {
        return $query->where('item_type', 'RAW_MATERIAL');
    }

    public function scopeFinishedGoods($query)
    {
        return $query->where('item_type', ItemType::FINISHED_GOOD);
    }

    public function vatProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatProductPostingGroup::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Item $item): void {
            if ($item->item_type !== ItemType::FINISHED_GOOD) {
                $item->production_bom_id = null;
                $item->routing_id = null;
            }
        });
    }
}
