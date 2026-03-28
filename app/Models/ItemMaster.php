<?php
// app/Models/ItemMaster.php

namespace App\Models;

use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use App\Enums\UomType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'item_code',
    'description',
    'item_type',
    'inventory_method',
    'vat_id',                        // NEW: Default VAT
    'general_posting_setup_id',      // NEW: GL accounts for sales/purchase
    'inventory_posting_setup_id',    // NEW: GL accounts for inventory
    'reference_cost',
    'reference_price',
    'shelf_life_days',
    'is_active',
])]
class ItemMaster extends Model
{
    use HasFactory;

    protected $table = 'item_masters';

    protected $casts = [
        'item_type' => ItemType::class,
        'inventory_method' => InventoryMethod::class,
        'reference_cost' => 'decimal:4',
        'reference_price' => 'decimal:4',
        'shelf_life_days' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'item_type' => ItemType::RAW_MATERIAL,
        'inventory_method' => InventoryMethod::FIFO,
        'reference_cost' => 0,
        'reference_price' => 0,
    ];

    /**
     * UOM assignments (M2M with pivot)
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

    /**
     * VAT for this item
     */
    public function vat(): BelongsTo
    {
        return $this->belongsTo(VatMaster::class, 'vat_id');
    }

    /**
     * General Posting Setup (GL accounts)
     */
    public function generalPostingSetup(): BelongsTo
    {
        return $this->belongsTo(GeneralPostingSetup::class, 'general_posting_setup_id');
    }

    /**
     * Inventory Posting Setup (GL accounts)
     */
    public function inventoryPostingSetup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingSetup::class, 'inventory_posting_setup_id');
    }

    /**
     * Vendor items (M2M with full pivot data)
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
     * Get preferred vendor
     */
    public function preferredVendor(): ?Vendor
    {
        return $this->vendors()
            ->wherePivot('is_preferred', true)
            ->first();
    }

    /**
     * Categories (M2M) - includes Category, SubCategory, Family
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'item_category_assignments')
            ->withPivot('is_primary', 'sort_order');
    }

    /**
     * Get primary category
     */
    public function primaryCategory(): ?Category
    {
        return $this->categories()
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * SKUs for this item (across locations)
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
     * Get current effective VAT
     */
    public function getEffectiveVatAttribute(): ?VatMaster
    {
        // Item VAT > Category VAT > null
        return $this->vat
            ?? $this->primaryCategory()?->vat;
    }

    /**
     * Get effective posting setups
     */
    public function getEffectiveGeneralPostingSetupAttribute(): ?GeneralPostingSetup
    {
        return $this->generalPostingSetup
            ?? $this->primaryCategory()?->generalPostingSetup;
    }

    public function getEffectiveInventoryPostingSetupAttribute(): ?InventoryPostingSetup
    {
        return $this->inventoryPostingSetup
            ?? $this->primaryCategory()?->inventoryPostingSetup;
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
