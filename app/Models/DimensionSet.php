<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimensionSet extends Model
{
    use HasFactory;

    protected $fillable = ['description', 'dimension_hash'];

    public function entries(): HasMany
    {
        return $this->hasMany(DimensionSetEntry::class);
    }

    public function treeNodes(): HasMany
    {
        return $this->hasMany(DimensionSetTreeNode::class);
    }

    /**
     * Get dimension values as associative array [code => value_code]
     */
    public function asArray(): array
    {
        return $this->entries->mapWithKeys(function ($entry) {
            return [$entry->dimension_code => $entry->dimension_value_code];
        })->toArray();
    }

    /**
     * Get formatted string for display
     */
    public function getDisplayStringAttribute(): string
    {
        return $this->entries->map(function ($e) {
            return "{$e->dimension_code}: {$e->dimension_value_code}";
        })->implode(', ');
    }
}
