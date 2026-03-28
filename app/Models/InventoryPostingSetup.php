<?php
// app/Models/InventoryPostingSetup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPostingSetup extends Model
{
    use HasFactory;

    protected $table = 'inventory_posting_setups';

    protected $fillable = [
        'code',
        'description',
        'inventory_account',           // Inventory asset account
        'inventory_adjmt_account',       // Inventory adjustment
        'invt_accrual_account',        // Inventory accrual (interim)
        'cogs_account',                // Cost of Goods Sold
        'direct_cost_applied_account',
        'overhead_applied_account',
        'purchase_variance_account',
        'material_variance_account',
        'capacity_variance_account',
        'subcontracted_variance_account',
        'cap_overhead_variance_account',
        'mfg_overhead_variance_account',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Categories using this setup
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'inventory_posting_setup_id');
    }

    /**
     * Items using this setup directly
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItemMaster::class, 'inventory_posting_setup_id');
    }
}
