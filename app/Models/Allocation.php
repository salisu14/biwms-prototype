<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Allocation extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'allocations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'description',
        'total_percentage',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_percentage' => 'decimal:2',
    ];

    /**
     * Get the allocation lines (distributions) associated with this header.
     * This typically follows the Business Central pattern where an Allocation
     * Header defines a rule, and lines define the specific split.
     */
    public function lines(): HasMany
    {
        // Assuming a standard naming convention for the lines model
        return $this->hasMany(AllocationLine::class);
    }

    /**
     * Scope a query to find an allocation by its unique code.
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Determine if the allocation is fully distributed (100%).
     */
    public function isFullyAllocated(): bool
    {
        return (float) $this->total_percentage === 100.00;
    }
}
