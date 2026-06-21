<?php

namespace App\Models;

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $attributes = [
        'structural_type' => 'posting',
        'account_category' => 'asset',
        'income_balance' => 0,
        'direct_posting' => true,
        'blocked' => false,
    ];

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'account_number',
        'name',
        'search_name',
        'structural_type',
        'account_category',
        'income_balance',
        'totaling',
        'indentation',
        'bold',
        'italic',
        'underline',
        'show_opposite_sign',
        'new_page',
        'no_of_blank_lines',
        'direct_posting',
        'blocked',
        'blocked_from',
        'blocked_to',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'gen_bus_posting_group_id',
        'gen_prod_posting_group_id',
        'vat_bus_posting_group_id',
        'vat_prod_posting_group_id',
        'cost_type_no',
        'consol_debit_acc',
        'consol_credit_acc',
        'consol_translation_method',
        'parent_account_id',
        'balance',
        'balance_at_date',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'balance_at_date' => 'decimal:2',
        'direct_posting' => 'boolean',
        'blocked' => 'boolean',
        'bold' => 'boolean',
        'italic' => 'boolean',
        'underline' => 'boolean',
        'show_opposite_sign' => 'boolean',
        'new_page' => 'boolean',
        'indentation' => 'integer',
        'no_of_blank_lines' => 'integer',
        'blocked_from' => 'date',
        'blocked_to' => 'date',
        'structural_type' => AccountStructuralType::class,
        'account_category' => AccountCategory::class,
        'income_balance' => IncomeBalanceType::class,
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * General Business Posting Group relationship
     */
    public function genBusPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class, 'gen_bus_posting_group_id');
    }

    /**
     * General Product Posting Group relationship
     */
    public function genProdPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class, 'gen_prod_posting_group_id');
    }

    /**
     * VAT Product Posting Group relationship
     * FIX: The foreign key for 'vatProdPostingGroup' should be 'vat_prod_posting_group_id', not 'vat_bus_posting_group_id'
     */
    public function vatProdPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatProductPostingGroup::class, 'vat_prod_posting_group_id');
    }

    /**
     * VAT Business Posting Group relationship
     * FIX: The foreign key for 'vatBusPostingGroup' should be 'vat_bus_posting_group_id', not 'vat_prod_posting_group_id'
     */
    public function vatBusPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatBusinessPostingGroup::class, 'vat_bus_posting_group_id');
    }

    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    public function glEntries(): HasMany
    {
        return $this->hasMany(GlEntry::class, 'chart_of_account_id');
    }

    // ==================== BUSINESS METHODS ====================

    public function isPostingAccount(): bool
    {
        return $this->structural_type === AccountStructuralType::POSTING;
    }

    public function isTotalAccount(): bool
    {
        return $this->structural_type?->isTotal() ?? false;
    }

    public function formattedName(): string
    {
        return str_repeat('  ', $this->indentation ?? 0).$this->name;
    }

    /**
     * Check if account allows direct posting (Validation)
     */
    public function allowsDirectPosting(): bool
    {
        return $this->isPostingAccount() && $this->direct_posting && ! $this->blocked;
    }

    protected static function booted(): void
    {
        static::saving(function ($account) {
            // Auto-set Income/Balance destination based on category
            if ($account->account_category instanceof AccountCategory) {
                $account->income_balance = $account->account_category->isIncomeStatement()
                    ? IncomeBalanceType::INCOME_STATEMENT
                    : IncomeBalanceType::BALANCE_SHEET;
            }
        });
    }
}
