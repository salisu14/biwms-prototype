<?php
// app/Models/Item.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
