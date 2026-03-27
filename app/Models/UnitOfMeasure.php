<?php
// app/Models/UnitOfMeasure.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uom_code',
    'description',
    'is_base_uom',        // Is this a base unit (cannot be deleted)
    'uom_category',       // WEIGHT, VOLUME, LENGTH, PIECE, etc.
])]
class UnitOfMeasure extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'unit_of_measures';

    protected $casts = [
        'is_base_uom' => 'boolean',
    ];

    /**
     * Items using this UOM (via pivot with type)
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemMaster::class,
            'item_uom_assignments',
            'uom_id',
            'item_id'
        )->withPivot('uom_type', 'conversion_factor', 'is_default');
    }

    /**
     * Get all assignments
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ItemUomAssignment::class, 'uom_id');
    }

    /**
     * Get items using this as base UOM
     */
    public function itemsAsBase(): BelongsToMany
    {
        return $this->items()->wherePivot('uom_type', 'BASE');
    }

    /**
     * Get items using this for sales
     */
    public function itemsAsSales(): BelongsToMany
    {
        return $this->items()->wherePivot('uom_type', 'SALES');
    }

    /**
     * Get items using this for purchase
     */
    public function itemsAsPurchase(): BelongsToMany
    {
        return $this->items()->wherePivot('uom_type', 'PURCHASE');
    }

    /**
     * Scope: Base UOMs only
     */
    public function scopeBaseUoms($query)
    {
        return $query->where('is_base_uom', true);
    }

    /**
     * Scope: By category
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('uom_category', $category);
    }
}
