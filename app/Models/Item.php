<?php
// app/Models/Item.php

namespace App\Models;

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
        'item_number',
        'description',
        'description_2',
        'general_product_posting_group_id',
        'inventory_posting_group_id',
        'vat_prod_posting_group',
        'item_type',
        'costing_method',
        'unit_cost',
        'standard_cost',
        'last_direct_cost',
        'unit_price',
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
        'blocked',
        'sales_blocked',
        'purchasing_blocked',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'standard_cost' => 'decimal:4',
        'last_direct_cost' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'inventory' => 'decimal:4',
        'reorder_point' => 'decimal:4',
        'reorder_quantity' => 'decimal:4',
        'weight' => 'decimal:4',
        'volume' => 'decimal:4',
        'blocked' => 'boolean',
        'sales_blocked' => 'boolean',
        'purchasing_blocked' => 'boolean',
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
        return $this->belongsToMany(
            UnitOfMeasure::class,
            'item_uom_assignments',
            'item_id',
            'uom_id'
        )->withPivot('uom_type', 'conversion_factor', 'is_default', 'sort_order')
            ->orderByPivot('sort_order');
    }

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

    public function valueEntries() {
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
            'item_id',                 // Foreign key on pivot for THIS model (ItemMaster)
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
     * Ledger entries
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedger::class, 'item_id');
    }

    /**
     * Calculate current quantity
     */
    public function getTotalQuantityAttribute(): float
    {
        return $this->ledgerEntries()
            ->selectRaw('
                SUM(CASE
                    WHEN entry_type IN (\'RECEIPT\', \'TRANSFER_IN\', \'ADJUSTMENT_POS\') THEN quantity
                    WHEN entry_type IN (\'ISSUE\', \'TRANSFER_OUT\', \'SALE\', \'ADJUSTMENT_NEG\') THEN -quantity
                    ELSE 0
                END) as total
            ')
            ->value('total') ?? 0;
    }

    /**
     * Get quantity at specific location
     */
    public function quantityAtLocation(int $locationId): float
    {
        return $this->ledgerEntries()
            ->where('location_id', $locationId)
            ->selectRaw('
                SUM(CASE
                    WHEN entry_type IN (\'RECEIPT\', \'TRANSFER_IN\', \'ADJUSTMENT_POS\') THEN quantity
                    WHEN entry_type IN (\'ISSUE\', \'TRANSFER_OUT\', \'SALE\', \'ADJUSTMENT_NEG\') THEN -quantity
                    ELSE 0
                END) as total
            ')
            ->value('total') ?? 0;
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
