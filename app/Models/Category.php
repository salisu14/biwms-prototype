<?php
// app/Models/Category.php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'category_code',
        'category_name', // Added to fillable
        'hierarchy_path',
        'parent_id',
        'level',
        'category_type',
        'description',
        'attributes',
        'is_active',
        'sort_order', // Added to fillable for safety
    ];

    protected $casts = [
        'category_type' => CategoryType::class,
        'level' => 'integer',
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    // Prevent selecting JSON columns by default if needed
//    protected $hidden = ['metadata', 'attributes']; // hide JSON columns from queries
    // Hide JSON columns from queries to avoid DISTINCT issues
    protected $hidden = ['metadata', 'attributes', 'settings']; // Add your JSON column names here

    // Or use $guarded and explicitly select columns in queries
    protected $guarded = ['metadata']; // Don't mass-assign, also excludes from SELECT *


    /**
     * Parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child categories (subcategories)
     */
    public function children(): HasMany
    {
        // Safety check: ensure sort_order exists or fallback to id
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('category_name');
    }

    /**
     * All descendants (recursive)
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * All ancestors (recursive up)
     */
    public function ancestors()
    {
        $ancestors = collect();
        $current = $this;

        while ($current->parent) {
            $ancestors->push($current->parent);
            $current = $current->parent;
        }

        return $ancestors->reverse();
    }

    /**
     * Full path name (e.g., "Therapeutic > Immune Support > Adaptogens")
     */
    public function getFullPathNameAttribute(): string
    {
        $names = $this->ancestors()->pluck('category_name')->toArray();
        $names[] = (string) $this->category_name;
        return implode(' > ', $names);
    }

    /**
     * Items in this category
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemMaster::class,
            'item_category_assignments',
            'category_id',
            'item_id'
        )->withPivot('is_primary', 'sort_order')
            ->withTimestamps();
    }

    /**
     * Primary items (where this is the main category)
     */
    public function primaryItems(): BelongsToMany
    {
        return $this->items()->wherePivot('is_primary', true);
    }

    /**
     * Get breadcrumb array
     */
    public function getBreadcrumbAttribute(): array
    {
        return $this->ancestors()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'code' => $cat->category_code,
                'name' => $cat->category_name,
            ])
            ->push([
                'id' => $this->id,
                'code' => $this->category_code,
                'name' => $this->category_name,
            ])
            ->toArray();
    }

    /**
     * Scope: By type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('category_type', $type);
    }

    /**
     * Scope: Top level only
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: Active only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Leaf nodes (no children)
     */
    public function scopeLeafNodes($query)
    {
        return $query->whereNotExists(function ($subquery) {
            $subquery->selectRaw(1)
                ->from('categories as c')
                ->whereColumn('c.parent_id', 'categories.id');
        });
    }

    /**
     * Get all items in this category and subcategories
     */
    public function getAllItemsAttribute()
    {
        $categoryIds = $this->getAllDescendantIds();
        $categoryIds[] = $this->id;

        return ItemMaster::whereHas('categoryAssignments', function ($query) use ($categoryIds) {
            $query->whereIn('category_id', $categoryIds);
        })->get();
    }

    /**
     * Recursive helper: Get all descendant IDs
     */
    private function getAllDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }
        return $ids;
    }

    /**
     * Static: Build tree structure
     */
    public static function tree(string $type = null): array
    {
        $query = self::with('children')
            ->whereNull('parent_id')
            ->active()
            ->orderBy('category_name');

        if ($type) {
            $query->ofType($type);
        }

        return $query->get()->map(fn ($cat) => $cat->toTreeArray())->toArray();
    }

    /**
     * Convert to tree array
     */
    public function toTreeArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->category_code,
            'name' => $this->category_name,
            'type' => $this->category_type,
            'children' => $this->children->map(fn ($child) => $child->toTreeArray())->toArray(),
        ];
    }

    // Better: Add a scope for safe selects
    public function scopeSelectSafe($query)
    {
        return $query->select(
            'category_id',
            'category_code',
            'category_name',
            'category_type',
            'parent_id',
            'is_active',
            'sort_order'
        // Explicitly exclude JSON columns: metadata, attributes, etc.
        );
    }
}
