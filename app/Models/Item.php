<?php

namespace App\Models;

use App\Enums\CostingMethod;
use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use App\Enums\UomType;
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
        'base_unit_of_measure',
        'weight',
        'volume',
        'shelf_no',
        'item_tracking_code',
        'shelf_life_days',
        'is_active',
        'blocked',
        'sales_blocked',
        'purchasing_blocked',
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

    public function uoms(): BelongsToMany
    {
        return $this->belongsToMany(
            UnitOfMeasure::class,
            'item_uom_assignments', // Pivot table
            'item_id',             // Foreign key on pivot for Item
            'uom_id'               // Foreign key on pivot for Unit of Measure (FIXED)
        )
            ->withPivot(['uom_type', 'conversion_factor', 'is_default'])
            ->withTimestamps();
    }
    //    public function uoms(): BelongsToMany
    //    {
    //        return $this->belongsToMany(UnitOfMeasure::class, 'item_uom_assignments')
    //            ->withPivot(['uom_type', 'conversion_factor', 'is_default'])
    //            ->withTimestamps();
    //    }

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

    /**
     * Relationships
     */
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

    public function getTotalQuantityAttribute(): float
    {
        return (float) $this->ledgerEntries()->sum('quantity');
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
}
