<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Item;
use App\Models\UnitOfMeasure;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
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
        'cost_rollup' => 'decimal:8',
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
        return (float) $this->calculateCostDecimal();
    }

    public function calculateCostDecimal(): string
    {
        return $this->calculateCostRecursive([]);
    }

    /**
     * @param  array<int, bool>  $visitedBomIds
     */
    private function calculateCostRecursive(array $visitedBomIds): string
    {
        if (isset($visitedBomIds[$this->id])) {
            return DecimalMath::unitCost('0');
        }

        $visitedBomIds[$this->id] = true;
        $cost = DecimalMath::unitCost('0');

        foreach ($this->lines as $line) {
            if ($line->type === ProductionBomLine::TYPE_ITEM) {
                $lineCost = DecimalMath::mul(
                    $line->item?->unit_cost ?? '0',
                    $line->quantity_per,
                    DecimalPrecision::UNIT_COST_SCALE
                );
                $scrapMultiplier = DecimalMath::add(
                    '1',
                    DecimalMath::div($line->scrap_percent ?? '0', '100', DecimalPrecision::CONVERSION_SCALE),
                    DecimalPrecision::CONVERSION_SCALE
                );
                $cost = DecimalMath::add(
                    $cost,
                    DecimalMath::mul($lineCost, $scrapMultiplier, DecimalPrecision::UNIT_COST_SCALE),
                    DecimalPrecision::UNIT_COST_SCALE
                );

                continue;
            }

            if ($line->type !== ProductionBomLine::TYPE_PRODUCTION_BOM) {
                continue;
            }

            $relatedBom = $line->relatedBom;
            if (! $relatedBom) {
                continue;
            }

            $cost = DecimalMath::add(
                $cost,
                DecimalMath::mul(
                    $relatedBom->calculateCostRecursive($visitedBomIds),
                    $line->quantity_per,
                    DecimalPrecision::UNIT_COST_SCALE
                ),
                DecimalPrecision::UNIT_COST_SCALE
            );
        }

        return $cost;
    }

    /**
     * Get active/certified version for a specific date
     */
    public function getActiveVersion(?\DateTime $date = null): ?ProductionBomVersion
    {
        $checkDate = $date ?? now();

        return $this->versions()
            ->where('status', 'CERTIFIED')
            ->where(function ($query) use ($checkDate) {
                $query->whereNull('starting_date')
                    ->orWhere('starting_date', '<=', $checkDate);
            })
            ->where(function ($query) use ($checkDate) {
                $query->whereNull('ending_date')
                    ->orWhere('ending_date', '>=', $checkDate);
            })
            ->orderByDesc('starting_date')
            ->first();
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
