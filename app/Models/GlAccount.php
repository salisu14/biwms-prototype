<?php

namespace App\Models;

use App\Enums\GlAccountCategory;
use App\Enums\GlAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GlAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'gl_accounts';

    protected $fillable = [
        'account_no',
        'account_name',
        'account_type',
        'account_category',
        'parent_account_id',
        'balance_account_type',
        'income_balance',
        'debit_credit',
        'blocked',
        'direct_posting',
        'reconciliation_account',
        'no_of_blank_lines',
        'indentation',
        'totaling',
        'global_dimension_1_filter',
        'global_dimension_2_filter',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'last_modified_date_time',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'account_type' => GlAccountType::class,
        'account_category' => GlAccountCategory::class,
        'blocked' => 'boolean',
        'direct_posting' => 'boolean',
        'reconciliation_account' => 'boolean',
        'no_of_blank_lines' => 'integer',
        'indentation' => 'integer',
        'last_modified_date_time' => 'datetime',
    ];

    // Relationships
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'parent_account_id');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(GlAccount::class, 'parent_account_id');
    }

    public function vendorInvoicesPayable(): HasMany
    {
        return $this->hasMany(\App\Models\VendorInvoice::class, 'payable_gl_account_id');
    }

    public function vendorInvoicesExpense(): HasMany
    {
        return $this->hasMany(\App\Models\VendorInvoice::class, 'expense_gl_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'last_modified_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('account_category', $category);
    }

    // Business Logic
    public function isCapitalizable(): bool
    {
        // Define capitalizable account categories/types
        $capitalizableCategories = ['FIXED_ASSETS', 'CAPEX', 'CONSTRUCTION_IN_PROGRESS'];
        return in_array($this->account_category, $capitalizableCategories);
    }

    public function getFullAccountName(): string
    {
        return "{$this->account_no} - {$this->account_name}";
    }
}
