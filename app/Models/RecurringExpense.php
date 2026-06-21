<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringExpense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'description', 'vendor_id', 'category_id', 'category_code',
        'amount', 'currency_id', 'frequency', 'start_date', 'end_date',
        'last_occurrence_at', 'next_occurrence_at', 'interval',
        'is_active', 'auto_post', 'dimension_set_id',
        'shortcut_dimension_1_code', 'shortcut_dimension_2_code',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_occurrence_at' => 'date',
        'next_occurrence_at' => 'date',
        'is_active' => 'boolean',
        'auto_post' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function dimensionSet(): BelongsTo
    {
        return $this->belongsTo(DimensionSet::class);
    }

    public function isDue(): bool
    {
        return $this->is_active && $this->next_occurrence_at <= now();
    }
}
