<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceChangeTemplateLine extends Model
{
    protected $fillable = [
        'template_id',
        'item_id',
        'category_id',
        'business_id',
        'customer_group_id',
        'current_unit_price',
        'new_unit_price',
        'adjustment_percent',
        'adjustment_amount',
        'applied_at',
    ];

    protected $casts = [
        'current_unit_price' => 'decimal:4',
        'new_unit_price' => 'decimal:4',
        'adjustment_percent' => 'decimal:4',
        'adjustment_amount' => 'decimal:4',
        'applied_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PriceChangeTemplate::class, 'template_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
