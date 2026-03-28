<?php
// app/Models/GeneralPostingSetup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneralPostingSetup extends Model
{
    use HasFactory;

    protected $table = 'general_posting_setups';

    protected $fillable = [
        'code',
        'description',
        'sales_account',           // GL Account for sales
        'sales_credit_account',    // GL Account for sales returns/credits
        'purchase_account',        // GL Account for purchases
        'purchase_credit_account', // GL Account for purchase returns
        'cogs_account',            // Cost of Goods Sold
        'inventory_adjustment_account',
        'direct_cost_applied_account',
        'overhead_applied_account',
        'purchase_variance_account',
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
        return $this->hasMany(Category::class, 'general_posting_setup_id');
    }

    /**
     * Items using this setup directly
     */
    public function items(): HasMany
    {
        return $this->hasMany(ItemMaster::class, 'general_posting_setup_id');
    }
}
