<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents specific value-level constraints when a Dimension Combination
 * is set to 'Limited'.
 */
class DimensionValueCombination extends Model
{
    protected $fillable = [
        'dimension_combination_id',
        'dimension_1_value_code',
        'dimension_2_value_code',
        'blocked',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    /**
     * The parent dimension rule this value restriction belongs to.
     */
    public function dimensionCombination(): BelongsTo
    {
        return $this->belongsTo(DimensionCombination::class);
    }
}
