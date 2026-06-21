<?php

namespace App\Models;

use App\Enums\DimensionCombinationRestriction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents the relationship rules between two high-level Dimensions.
 */
class DimensionCombination extends Model
{
    protected $fillable = [
        'dimension_1_code',
        'dimension_2_code',
        'combination_type',
    ];

    protected $casts = [
        'combination_type' => DimensionCombinationRestriction::class,
    ];

    /**
     * Get the specific value-level restrictions for this combination.
     * Only relevant if combination_type is 'limited'.
     */
    public function valueCombinations(): HasMany
    {
        return $this->hasMany(DimensionValueCombination::class);
    }

    /**
     * Logic to determine if a specific pair of values is allowed.
     */
    public function isAllowed(string $val1, string $val2): bool
    {
        return match ($this->combination_type) {
            DimensionCombinationRestriction::NoLimitation => true,
            DimensionCombinationRestriction::Blocked => false,
            DimensionCombinationRestriction::Limited => ! $this->valueCombinations()
                ->where('dimension_1_value_code', $val1)
                ->where('dimension_2_value_code', $val2)
                ->where('blocked', true)
                ->exists(),
        };
    }
}
