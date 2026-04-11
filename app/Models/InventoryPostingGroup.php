<?php

// app/Models/InventoryPostingGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPostingGroup extends Model
{
    use HasFactory;

    protected $table = 'inventory_posting_groups';

    protected $fillable = [
        'code',
        'description',
        'blocked',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function inventoryPostingSetups(): HasMany
    {
        return $this->hasMany(InventoryPostingSetup::class);
    }

    public function itemLedgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedgerEntry::class);
    }

    // Get inventory account for location (or default)
    public function getInventoryAccount(?int $locationId = null): ?ChartOfAccount
    {
        $setup = $this->inventoryPostingSetups()
            ->where('location_id', $locationId)
            ->first();

        if (! $setup && $locationId) {
            // Try default (null location)
            $setup = $this->inventoryPostingSetups()
                ->whereNull('location_id')
                ->first();
        }

        return $setup?->inventoryAccount;
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }
}
