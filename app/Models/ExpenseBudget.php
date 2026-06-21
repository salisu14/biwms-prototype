<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_name', 'fiscal_year',
        'account_type', 'category_code',
        'shortcut_dimension_1_code', 'shortcut_dimension_2_code',
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
        'annual_total', 'is_active', 'dimension_set_id', 'currency_id',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'january' => 'decimal:4',
        'february' => 'decimal:4',
        'march' => 'decimal:4',
        'april' => 'decimal:4',
        'may' => 'decimal:4',
        'june' => 'decimal:4',
        'july' => 'decimal:4',
        'august' => 'decimal:4',
        'september' => 'decimal:4',
        'october' => 'decimal:4',
        'november' => 'decimal:4',
        'december' => 'decimal:4',
        'annual_total' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function getMonthValue(int $month): float
    {
        $months = [
            1 => 'january', 2 => 'february', 3 => 'march',
            4 => 'april', 5 => 'may', 6 => 'june',
            7 => 'july', 8 => 'august', 9 => 'september',
            10 => 'october', 11 => 'november', 12 => 'december',
        ];

        return $this->{$months[$month]} ?? 0;
    }

    public function dimensionSet(): BelongsTo
    {
        return $this->belongsTo(DimensionSet::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
