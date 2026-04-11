<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\COGSCategory;
use App\Enums\RevenueCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_type', 'category_code', 'category_type',
        'description', 'notes',
        'is_direct', 'is_variable', 'is_controllable',
        'category_id',
        'expense_account_id', 'contra_account_id',
        'posting_rules',
        'default_dimension_1', 'default_dimension_2',
        'is_active',
    ];

    protected $casts = [
        'account_type' => AccountType::class,
        'is_direct' => 'boolean',
        'is_variable' => 'boolean',
        'is_controllable' => 'boolean',
        'posting_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function budgets(): HasMany
    {
        // We link the Budget's 'category_code' to the Category's 'category_code'
        return $this->hasMany(ExpenseBudget::class, 'category_code', 'category_code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ExpenseTransaction::class, 'category_code', 'category_code');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'contra_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getCategoryEnum()
    {
        return match ($this->category_type) {
            'expense' => ExpenseCategory::from($this->category_code),
            'revenue' => RevenueCategory::from($this->category_code),
            'cogs' => COGSCategory::from($this->category_code),
            default => null,
        };
    }
}
