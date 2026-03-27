<?php
// app/Models/ItemCategoryAssignment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCategoryAssignment extends Pivot
{
    use HasFactory;

    protected $table = 'item_category_assignments';
    protected $primaryKey = 'assignment_id';

    public $incrementing = true;

    protected $fillable = [
        'item_id',
        'category_id',
        'is_primary',
        'sort_order'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
