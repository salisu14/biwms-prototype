<?php

namespace App\Models\Manufacturing;

use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBomVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_bom_id',
        'version_code',
        'description',
        'status', // CERTIFIED, UNDER_DEVELOPMENT, CLOSED
        'starting_date',
        'ending_date',
        'unit_of_measure_code',
        'quantity_per',
        'cost_rollup',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'starting_date' => 'date',
        'ending_date' => 'date',
        'quantity_per' => 'decimal:8',
        'cost_rollup' => 'decimal:8',
    ];

    public function productionBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_code', 'uom_code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionBomVersionLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    /**
     * Check if version is active (certified and within date range)
     */
    public function isActive(?\DateTime $date = null): bool
    {
        $checkDate = $date ?? now();

        return $this->status === 'CERTIFIED'
            && ($this->starting_date === null || $this->starting_date <= $checkDate)
            && ($this->ending_date === null || $this->ending_date >= $checkDate);
    }

    /**
     * Get active version line for a specific item
     */
    public function getLineForItem(int $itemId): ?ProductionBomVersionLine
    {
        return $this->lines()->where('item_id', $itemId)->first();
    }
}
