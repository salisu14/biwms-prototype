<?php

namespace App\Models;

use App\Enums\DimensionValueType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimensionValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'dimension_id', 'code', 'name', 'dimension_value_type',
        'parent_id', 'indentation', 'blocked', 'starting_date', 'ending_date',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'starting_date' => 'date',
        'ending_date' => 'date',
        'dimension_value_type' => DimensionValueType::class,
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DimensionValue::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DimensionValue::class, 'parent_id');
    }

    public function setEntries(): HasMany
    {
        return $this->hasMany(DimensionSetEntry::class, 'dimension_value_code', 'code');
    }

    public function scopeStandard($query)
    {
        return $query->where('dimension_value_type', 'standard');
    }

    public function scopeActive($query)
    {
        return $query->where('blocked', false)
            ->where(function ($q) {
                $q->whereNull('starting_date')->orWhere('starting_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ending_date')->orWhere('ending_date', '>=', now());
            });
    }
}
