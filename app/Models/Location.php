<?php

// app/Models/Location.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'location_type',
        'temperature_zone',
        'address',
        'directed_put_away_and_pick',
        'bin_mandatory',
        'require_receive',
        'require_shipment',
        'require_put_away',
        'require_pick',
        'receipt_bin_code',
        'shipment_bin_code',
        'open_shop_floor_bin_code',
        'inbound_production_bin_code',
        'outbound_production_bin_code',
        'adjustment_bin_code',
        'is_active',
        'blocked',
        'sort_order',
    ];

    protected $casts = [
        'directed_put_away_and_pick' => 'boolean',
        'bin_mandatory' => 'boolean',
        'require_receive' => 'boolean',
        'require_shipment' => 'boolean',
        'require_put_away' => 'boolean',
        'require_pick' => 'boolean',
        'is_active' => 'boolean',
        'blocked' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    public function inventoryPostingSetups(): HasMany
    {
        return $this->hasMany(InventoryPostingSetup::class);
    }

    public function warehouseReceipts(): HasMany
    {
        return $this->hasMany(WarehouseReceipt::class);
    }

    public function warehouseShipments(): HasMany
    {
        return $this->hasMany(WarehouseShipment::class);
    }

    public function itemJournalBatches(): HasMany
    {
        return $this->hasMany(ItemJournalBatch::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    // Check if using advanced WMS
    public function usesAdvancedWms(): bool
    {
        return $this->directed_put_away_and_pick;
    }

    // Check if warehouse documents required
    public function requiresWarehouseReceipt(): bool
    {
        return $this->require_receive || $this->directed_put_away_and_pick;
    }

    public function requiresWarehouseShipment(): bool
    {
        return $this->require_shipment || $this->directed_put_away_and_pick;
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('blocked', false);
    }

    public function scopeWithWms($query)
    {
        return $query->where('directed_put_away_and_pick', true);
    }
}
