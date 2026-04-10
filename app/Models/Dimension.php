<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'code_caption', 'filter_caption',
        'description', 'blocked', 'dimension_type', 'global_dimension_no'
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(DimensionValue::class);
    }

    public function defaultDimensions(): HasMany
    {
        return $this->hasMany(DefaultDimension::class, 'dimension_code', 'code');
    }

    public function scopeGlobal($query)
    {
        return $query->where('dimension_type', 'global');
    }

    public function scopeShortcut($query)
    {
        return $query->whereIn('dimension_type', ['global', 'shortcut']);
    }

    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }
}
