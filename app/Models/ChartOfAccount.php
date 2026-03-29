<?php
// app/Models/ChartOfAccount.php

namespace App\Models;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_number',
        'name',
        'account_type',
        'account_category',
        'balance',
        'direct_posting',
        'blocked',
        'parent_account_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'direct_posting' => 'boolean',
        'blocked' => 'boolean',
        'account_type' => AccountType::class,
        'account_category' => AccountCategory::class,
    ];

    // Parent account (for hierarchical COA)
    public function parentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_account_id');
    }

    // Child accounts
    public function childAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_account_id');
    }

    // Relationships to posting setups
    public function generalPostingSetupLines(): HasMany
    {
        return $this->hasMany(GeneralPostingSetupLine::class);
    }

    public function inventoryPostingSetupsAsInventory(): HasMany
    {
        return $this->hasMany(InventoryPostingSetup::class, 'inventory_account_id');
    }

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('account_type', $type);
    }

    public function scopeRevenue($query)
    {
        return $query->where('account_type', 'REVENUE');
    }

    public function scopeCogs($query)
    {
        return $query->where('account_type', 'COGS');
    }

    public function scopeInventory($query)
    {
        return $query->where('account_category', 'INVENTORY');
    }
}
