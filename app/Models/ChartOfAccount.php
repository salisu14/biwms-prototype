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

    protected static function booted()
    {
        static::saving(function ($account) {
            // Automatically classify Income Statement vs Balance Sheet based on Account Type
            if ($account->account_type) {
                $incomeBalanceTypes = [
                    AccountType::REVENUE->value,
                    AccountType::COGS->value,
                    AccountType::EXPENSE->value,
                ];

                if (in_array($account->account_type->value, $incomeBalanceTypes)) {
                    $account->income_balance = \App\Enums\IncomeBalanceType::INCOME_STATEMENT;
                } else {
                    $account->income_balance = \App\Enums\IncomeBalanceType::BALANCE_SHEET;
                }
            }
        });
    }

    protected $fillable = [
        'account_number',
        'name',
        'account_type',
        'account_category',
        'income_balance',
        'gl_account_type',
        'totaling',
        'indentation',
        'bold',
        'show_opposite_sign',
        'new_page',
        'balance',
        'direct_posting',
        'blocked',
        'parent_account_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'direct_posting' => 'boolean',
        'blocked' => 'boolean',
        'bold' => 'boolean',
        'show_opposite_sign' => 'boolean',
        'new_page' => 'boolean',
        'account_type' => AccountType::class,
        'account_category' => AccountCategory::class,
        'gl_account_type' => \App\Enums\GlAccountType::class,
        'income_balance' => \App\Enums\IncomeBalanceType::class,
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

    public function isTotalAccount(): bool
    {
        return in_array($this->gl_account_type, [
            \App\Enums\GlAccountType::TOTAL,
            \App\Enums\GlAccountType::END_TOTAL,
        ]);
    }

    public function isIncomeStatement(): bool
    {
        return $this->income_balance === \App\Enums\IncomeBalanceType::INCOME_STATEMENT;
    }
}
