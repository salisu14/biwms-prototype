<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DimensionSetEntry extends Model
{
    use HasFactory;

    protected $table = 'dimension_set_entries';

    protected $fillable = [
        'dimension_set_id', 'dimension_code', 'dimension_value_code',
        'dimension_name', 'dimension_value_name'
    ];

    public function dimensionSet(): BelongsTo
    {
        return $this->belongsTo(DimensionSet::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_code', 'code');
    }

    public function dimensionValue(): BelongsTo
    {
        return $this->belongsTo(DimensionValue::class, 'dimension_value_code', 'code');
    }
}
