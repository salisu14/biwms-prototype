<?php
// app/Models/ItemMaster.php

namespace App\Models;

use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'item_code',
    'description',
    'item_type',
    'inventory_method',
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
        'shelf_life_days' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'item_type' => ItemType::RAW_MATERIAL,
        'inventory_method' => InventoryMethod::FIFO,
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
    public function getUomByType(string $type): ?UnitOfMeasure
    {
        return $this->uoms()
            ->wherePivot('uom_type', $type)
            ->wherePivot('is_default', true)
            ->first();
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
     * Categories (M2M)
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'item_category_assignments')
            ->withPivot('is_primary', 'sort_order');
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

    // Scopes and accessors...
    public function scopeOfType($query, ItemType $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeWithMethod($query, InventoryMethod $method)
    {
        return $query->where('inventory_method', $method);
    }

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
}
