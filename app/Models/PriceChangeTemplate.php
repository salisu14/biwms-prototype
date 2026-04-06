<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceChangeTemplate extends Model
{
    protected $fillable = [
        'name',
        'adjustment_type',
        'value',
        'base',
        'rounding',
        'status', // draft, approved, applied
        'effective_from',
        'effective_to'
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PriceChangeTemplateLine::class, 'template_id');
    }
}
