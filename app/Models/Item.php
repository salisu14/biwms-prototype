<?php

// app/Models/Item.php

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
        'vat_prod_posting_group',
        'vat_product_posting_group_id',
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
     * UOM assignments
     */
    public function uomAssignments(): HasMany
    {
        return $this->hasMany(ItemUomAssignment::class, 'item_id');
    }

    /**
     * All UOMs via pivot
     */
    public function uoms(): BelongsToMany
    {
        return $this->belongsToMany(UnitOfMeasure::class, 'item_uom_assignments', 'item_id', 'uom_id')
            ->using(ItemUomAssignment::class)
            ->withPivot(['uom_type', 'conversion_factor', 'is_default'])
            ->withTimestamps();
    }

    //    public function uoms(): BelongsToMany
    //    {
    //        return $this->belongsToMany(
    //            UnitOfMeasure::class,
    //            'item_uom_assignments',
    //            'item_id',
    //            'uom_id'
    //        )->withPivot('uom_type', 'conversion_factor', 'is_default', 'sort_order')
    //            ->orderByPivot('sort_order');
    //    }

    /**
     * Get UOM by type
     */
    public function getUomByType(UomType|string $type): ?UnitOfMeasure
    {
        $typeValue = is_string($type) ? $type : $type->value;

        return $this->uoms()
            ->wherePivot('uom_type', $typeValue)
            ->wherePivot('is_default', true)
            ->first();
    }

    public function baseUnitOfMeasure(): BelongsToMany
    {
        return $this->belongsToMany(UnitOfMeasure::class, 'item_uom_assignments', 'item_id', 'uom_id')
            ->wherePivot('uom_type', 'BASE')
            ->wherePivot('is_default', true)
            ->withPivot(['conversion_factor']);
    }

    /**
     * Get default UOM for type
     */
    public function getDefaultUom(UomType|string $type): ?UnitOfMeasure
    {
        return $this->getUomByType($type)
            ?? $this->uoms()->wherePivot('uom_type', is_string($type) ? $type : $type->value)->first();
    }

    // Relationships
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

    public function warehouseReceiptLines(): HasMany
    {
        return $this->hasMany(WarehouseReceiptLine::class);
    }

    public function warehouseShipmentLines(): HasMany
    {
        return $this->hasMany(WarehouseShipmentLine::class);
    }

    public function itemLedgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedgerEntry::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    public function vat(): BelongsTo
    {
        return $this->belongsTo(VatMaster::class, 'vat_id');
    }

    public function vatProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatProductPostingGroup::class);
    }

    public function generalPostingSetup(): BelongsTo
    {
        return $this->belongsTo(GeneralPostingSetup::class, 'general_posting_setup_id');
    }

    public function inventoryPostingSetup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingSetup::class, 'inventory_posting_setup_id');
    }

    // Get inventory account for location
    public function getInventoryAccount(?int $locationId = null): ?ChartOfAccount
    {
        return $this->inventoryPostingGroup->getInventoryAccount($locationId);
    }

    // Get posting setup with a business group
    public function getPostingSetupWith(GeneralBusinessPostingGroup $businessGroup): ?GeneralPostingSetup
    {
        return $this->generalProductPostingGroup->getSetupWith($businessGroup);
    }

    // Check if inventory item
    public function isInventoryItem(): bool
    {
        return $this->item_type === 'INVENTORY';
    }

    // Check if service item
    public function isServiceItem(): bool
    {
        return $this->item_type === 'SERVICE';
    }

    // Calculate inventory value
    public function inventoryValue(): float
    {
        return $this->inventory * $this->unit_cost;
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeAvailableForSale($query)
    {
        return $query->where('blocked', false)
            ->where('sales_blocked', false)
            ->where('item_type', '!=', 'SERVICE');
    }

    public function scopeAvailableForPurchase($query)
    {
        return $query->where('blocked', false)
            ->where('purchasing_blocked', false);
    }

    public function scopeInventoryItems($query)
    {
        return $query->where('item_type', 'INVENTORY');
    }

    public function valueEntries()
    {
        return $this->hasMany(ValueEntry::class);
    }

    /**
     * Vendor items
     */
    public function vendorItems(): HasMany
    {
        return $this->hasMany(VendorItem::class, 'item_id');
    }

    /**
     * Vendors who supply this item
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'vendor_items')
            ->withPivot('vendor_item_number', 'vendor_item_name', 'is_preferred');
    }

    /**
     * Get preferred vendor - RENAMED to avoid conflict
     */
    public function getPreferredVendor(): ?Vendor  // CHANGED: was preferredVendor()
    {
        return $this->vendors()
            ->wherePivot('is_preferred', true)
            ->first();
    }

    /**
     * Categories (M2M)
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,           // Related model
            'item_category_assignments', // Pivot table
            'item_id',                 // Foreign key on pivot for THIS model (Item)
            'category_id',             // Foreign key on pivot for RELATED model (Category)
            'id',                      // Local key on THIS model
            'id'                       // Local key on RELATED model
        )->withPivot('is_primary', 'sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Category assignments for repeater form
     */
    public function categoryAssignments(): HasMany
    {
        return $this->hasMany(ItemCategoryAssignment::class, 'item_id');
    }

    /**
     * Get primary category - RENAMED to avoid conflict
     */
    public function getPrimaryCategory(): ?Category  // CHANGED: was primaryCategory()
    {
        return $this->categories()
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * SKUs for this item
     */
    public function skus(): HasMany
    {
        return $this->hasMany(ItemSku::class, 'item_id');
    }

    /**
     * Ledger entries (Physical movements)
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedgerEntry::class, 'item_id');
    }

    /**
     * Calculate current quantity
     */
    public function getTotalQuantityAttribute(): float
    {
        return $this->ledgerEntries()
            ->sum('quantity') ?? 0;
    }

    /**
     * Get quantity at specific location
     */
    public function quantityAtLocation(int $locationId): float
    {
        return $this->ledgerEntries()
            ->where('location_id', $locationId)
            ->sum('quantity') ?? 0;
    }

    /**
     * Get effective VAT
     */
    public function getEffectiveVatAttribute(): ?VatMaster
    {
        return $this->vat;
    }

    /**
     * Get effective posting setups
     */
    public function getEffectiveGeneralPostingSetupAttribute(): ?GeneralPostingSetup
    {
        return $this->generalPostingSetup;
    }

    public function getEffectiveInventoryPostingSetupAttribute(): ?InventoryPostingSetup
    {
        return $this->inventoryPostingSetup;
    }

    /**
     * Scopes
     */
    public function scopeOfType($query, ItemType $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeWithMethod($query, InventoryMethod $method)
    {
        return $query->where('inventory_method', $method);
    }

    /**
     * Accessors
     */
    public function getItemTypeLabelAttribute(): string
    {
        return $this->item_type->label();
    }

    public function getInventoryMethodLabelAttribute(): string
    {
        return $this->inventory_method->label();
    }

    public function getRequiresCostLayersAttribute(): bool
    {
        return $this->inventory_method->requiresCostLayerTracking();
    }

    public function getCurrentStandardCostAttribute(): float
    {
        return $this->vendorItems()
            ->where('is_active', true)
            ->min('unit_cost') ?? (float) $this->reference_cost;
    }

    /**
     * Get all UOMs organized by type
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
}
