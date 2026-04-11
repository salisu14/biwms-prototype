<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_code',
        'category_name',
        'hierarchy_path',
        'parent_id',
        'level',
        'sort_order',
        'category_type',
        'description',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'category_type' => CategoryType::class,
        'attributes' => 'array',
        'is_active' => 'boolean',
        'level' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Parent category (self-referencing)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * All descendants (recursive)
     */
    public function descendants(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->with('descendants');
    }

    /**
     * Items in this category
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            Item::class,
            'item_category_assignments',
            'category_id',      // Foreign key on pivot table for this model
            'item_id',          // Foreign key on pivot table for related model
            'id',               // Local key on this model
            'id'                // Local key on related model
        )
            ->withPivot('is_primary', 'sort_order');
    }

    /**
     * Get full path name (e.g., "Therapeutic > Immune Support > Adaptogens")
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->category_name];
        $parent = $this->parent;

        while ($parent) {
            $path[] = $parent->category_name;
            $parent = $parent->parent;
        }

        return implode(' > ', array_reverse($path));
    }

    /**
     * Scope by category type
     */
    public function scopeOfType($query, CategoryType $type)
    {
        return $query->where('category_type', $type);
    }

    /**
     * Scope active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope root level only
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope by level
     */
    public function scopeAtLevel($query, int $level)
    {
        return $query->where('level', $level);
    }
}
