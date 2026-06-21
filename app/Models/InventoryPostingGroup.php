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
        $query = $this->inventoryPostingSetups()
            ->whereNotNull('inventory_account_id');

        if ($locationId) {
            $setup = (clone $query)
                ->where(function ($builder) use ($locationId): void {
                    $builder->where('location_id', $locationId)
                        ->orWhereNull('location_id');
                })
                ->orderByRaw('location_id IS NULL')
                ->first()
                ?? $query->first();
        } else {
            $setup = (clone $query)
                ->whereNull('location_id')
                ->first()
                ?? $query->first();
        }

        return $setup?->inventoryAccount;
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }
}
