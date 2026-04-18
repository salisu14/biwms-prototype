<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAllocation extends Model
{
    protected $fillable = [
        'expense_transaction_id', 'allocation_basis', 'allocation_type',
        'allocation_percentage', 'allocated_amount', 'target_dimension_1',
        'target_dimension_2', 'target_gl_account_id', 'gl_entry_id', 'dimension_set_id',
    ];

    protected $casts = [
        'allocation_percentage' => 'decimal:2',
        'allocated_amount' => 'decimal:4',
    ];

    public function expenseTransaction(): BelongsTo
    {
        return $this->belongsTo(ExpenseTransaction::class);
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'target_gl_account_id');
    }

    public function dimensionSet(): BelongsTo
    {
        return $this->belongsTo(DimensionSet::class);
    }
}
