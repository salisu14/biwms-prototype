<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimensionSetTreeNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_dimension_set_id',
        'dimension_value_id',
        'dimension_set_id',
        'in_use',
    ];

    protected $casts = [
        'in_use' => 'boolean',
    ];

    public function dimensionSet(): BelongsTo
    {
        return $this->belongsTo(DimensionSet::class);
    }

    public function dimensionValue(): BelongsTo
    {
        return $this->belongsTo(DimensionValue::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_dimension_set_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_dimension_set_id');
    }

    /**
     * Scope for root nodes (parent_id = 0)
     */
    public function scopeRoot($query)
    {
        return $query->where('parent_dimension_set_id', 0);
    }

    /**
     * Scope for active/in-use nodes
     */
    public function scopeInUse($query)
    {
        return $query->where('in_use', true);
    }
}
