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

    protected $primaryKey = 'id';

    protected $fillable = [
        'category_code',
        'category_name',
        'hierarchy_path',      // Original: materialized path for hierarchy
        'parent_id',
        'category_type',
        'description',
        'attributes',
        'is_active',
        'sort_order',
        // 3NF Additions:
        'vat_id',
        'general_posting_setup_id',
        'inventory_posting_setup_id',
    ];

    protected $casts = [
        'category_type' => CategoryType::class,
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    // Hide JSON columns to avoid PostgreSQL DISTINCT issues
    protected $hidden = ['attributes'];

    /**
     * Parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id', 'id');
    }

    /**
     * Child categories (subcategories)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id', 'id')
            ->orderBy('sort_order')
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
        // Use hierarchy_path if available, otherwise build from ancestors
        if ($this->hierarchy_path) {
            $names = explode(' > ', $this->hierarchy_path);
            return $this->hierarchy_path;
        }

        $names = $this->ancestors()->pluck('category_name')->toArray();
        $names[] = (string) $this->category_name;
        return implode(' > ', $names);
    }

    /**
     * Get level from hierarchy_path or ancestors
     */
    public function getLevelAttribute(): int
    {
        if ($this->hierarchy_path) {
            return substr_count($this->hierarchy_path, ' > ') + 1;
        }
        return $this->ancestors()->count() + 1;
    }

    /**
     * Check if this is a Category (level 1)
     */
    public function getIsCategoryAttribute(): bool
    {
        return $this->level === 1;
    }

    /**
     * Check if this is a SubCategory (level 2)
     */
    public function getIsSubCategoryAttribute(): bool
    {
        return $this->level === 2;
    }

    /**
     * Check if this is a Family (level 3)
     */
    public function getIsFamilyAttribute(): bool
    {
        return $this->level >= 3;
    }

    /**
     * 3NF: VAT for this category
     */
    public function vat(): BelongsTo
    {
        return $this->belongsTo(VatMaster::class, 'vat_id');
    }

    /**
     * 3NF: General Posting Setup (GL accounts)
     */
    public function generalPostingSetup(): BelongsTo
    {
        return $this->belongsTo(GeneralPostingSetup::class, 'general_posting_setup_id');
    }

    /**
     * 3NF: Inventory Posting Setup (GL accounts)
     */
    public function inventoryPostingSetup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingSetup::class, 'inventory_posting_setup_id');
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
                'id' => $cat->category_id,
                'code' => $cat->category_code,
                'name' => $cat->category_name,
            ])
            ->push([
                'id' => $this->category_id,
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
     * Scope: Top level only (Categories)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: By level (using hierarchy_path)
     */
    public function scopeByLevel($query, int $level)
    {
        // Use hierarchy_path pattern matching for efficiency
        if ($level === 1) {
            return $query->whereNull('parent_id');
        }
        return $query->whereRaw("hierarchy_path LIKE ? ESCAPE '\\'", [str_repeat('% > ', $level - 1) . '%']);
    }

    /**
     * Scope: Categories only (level 1)
     */
    public function scopeCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: SubCategories only (level 2)
     */
    public function scopeSubCategories($query)
    {
        return $query->whereNotNull('parent_id')
            ->whereRaw("hierarchy_path NOT LIKE '% > % > %'");
    }

    /**
     * Scope: Families only (level 3+)
     */
    public function scopeFamilies($query)
    {
        return $query->whereRaw("hierarchy_path LIKE '% > % > %'");
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
                ->whereColumn('c.parent_id', 'categories.category_id');
        });
    }

    /**
     * Get all descendant IDs using hierarchy_path (efficient)
     */
    public function getAllDescendantIds(): array
    {
        return self::where('hierarchy_path', 'like', $this->hierarchy_path . ' > %')
            ->orWhere('hierarchy_path', 'like', '% > ' . $this->category_name . ' > %')
            ->pluck('category_id')
            ->toArray();
    }

    /**
     * Static: Build tree structure
     */
    public static function tree(string $type = null): array
    {
        $query = self::with('children')
            ->whereNull('parent_id')
            ->active()
            ->orderBy('sort_order')
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
            'id' => $this->category_id,
            'code' => $this->category_code,
            'name' => $this->category_name,
            'type' => $this->category_type,
            'level' => $this->level,
            'children' => $this->children->map(fn ($child) => $child->toTreeArray())->toArray(),
        ];
    }

    /**
     * Scope: Safe select (exclude JSON columns)
     */
    public function scopeSelectSafe($query)
    {
        return $query->select(
            'category_id',
            'category_code',
            'category_name',
            'hierarchy_path',
            'parent_id',
            'category_type',
            'description',
            'is_active',
            'sort_order',
            'vat_id',
            'general_posting_setup_id',
            'inventory_posting_setup_id'
        // Excluded: attributes (JSON)
        );
    }

    /**
     * Boot: Auto-update hierarchy_path on save
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($category) {
            if ($category->parent_id) {
                $parent = self::find($category->parent_id);
                $category->hierarchy_path = $parent->hierarchy_path
                    ? $parent->hierarchy_path . ' > ' . $category->category_name
                    : $parent->category_name . ' > ' . $category->category_name;
            } else {
                $category->hierarchy_path = $category->category_name;
            }
        });
    }
}
