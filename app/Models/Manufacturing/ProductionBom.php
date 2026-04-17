<?php

namespace App\Models\Manufacturing;

use App\Models\Item;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBom extends Model
{
    use HasFactory;

    protected $table = 'production_boms';

    protected $fillable = [
        'code',
        'description',
        'item_id', // Parent item
        'unit_of_measure_code',
        'status', // CERTIFIED, UNDER_DEVELOPMENT, CLOSED
        'version', // For version control
        'starting_date',
        'ending_date',

        // Posting
        'low_level_code', // For BOM explosion order
        'cost_rollup',

        // User tracking
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'starting_date' => 'date',
        'ending_date' => 'date',
        'cost_rollup' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_code', 'uom_code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionBomLine::class, 'production_bom_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductionBomVersion::class, 'production_bom_id');
    }

    /**
     * Calculate BOM cost recursively
     */
    public function calculateCost(): float
    {
        $cost = 0;
        foreach ($this->lines as $line) {
            if ($line->type === 'ITEM') {
                $itemCost = $line->item?->unit_cost ?? 0;
                $cost += ($itemCost * $line->quantity_per) * (1 + $line->scrap_percent / 100);
            } elseif ($line->type === 'PRODUCTION_BOM') {
                // Recursive BOM
                $subBom = self::find($line->production_bom_id);
                if ($subBom) {
                    $cost += ($subBom->calculateCost() * $line->quantity_per);
                }
            }
        }

        return $cost;
    }

    protected static function booted(): void
    {
        static::creating(function ($routing) {
            $routing->created_by = auth()->id();
        });

        static::updating(function ($routing) {
            $routing->last_modified_by = auth()->id();
        });
    }
}
