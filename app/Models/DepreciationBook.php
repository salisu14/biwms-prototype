<?php

namespace App\Models;

use App\Enums\DepreciationMethod;
use App\Enums\DepreciationCalculationMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model for Depreciation Books
 */
class DepreciationBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'description', 'book_type', 'is_default',
        'default_depreciation_method', 'default_calculation_method',
        'integrate_with_gl', 'use_rounding', 'rounding_precision',
        'align_fiscal_year', 'fiscal_year_start', 'is_active',
    ];

    protected $casts = [
        'default_depreciation_method' => DepreciationMethod::class,
        'default_calculation_method' => DepreciationCalculationMethod::class,
        'is_default' => 'boolean',
        'integrate_with_gl' => 'boolean',
        'use_rounding' => 'boolean',
        'align_fiscal_year' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FALedgerEntry::class);
    }
}
