<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultDimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_id', 'no', 'dimension_code', 'dimension_value_code', 'value_posting', 'blocked'
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'value_posting' => \App\Enums\ValuePosting::class,
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_code', 'code');
    }

    public function dimensionValue(): BelongsTo
    {
        return $this->belongsTo(DimensionValue::class, 'dimension_value_code', 'code');
    }

    public function scopeForEntity($query, string $tableId, string $no)
    {
        return $query->where('table_id', $tableId)->where('no', $no);
    }
}
