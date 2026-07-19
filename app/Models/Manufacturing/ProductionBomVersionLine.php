<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBomVersionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_bom_version_id',
        'line_number',
        'type', // ITEM, PRODUCTION_BOM
        'item_id',
        'production_bom_id_related',
        'description',
        'unit_of_measure_code',
        'quantity_per',
        'scrap_percent',
        'routing_link_code',
        'flushing_method', // MANUAL, FORWARD, BACKWARD
        'position',
        'position_2',
        'position_3',
        'lead_time_offset_days',
        'location_code',
        'bin_code',
    ];

    protected $casts = [
        'quantity_per' => 'decimal:8',
        'scrap_percent' => 'decimal:2',
        'lead_time_offset_days' => 'integer',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductionBomVersion::class, 'production_bom_version_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function relatedBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id_related');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_code', 'uom_code');
    }

    /**
     * Calculate expected quantity including scrap
     */
    public function getExpectedQuantity(float $parentQuantity): float
    {
        return (float) $this->getExpectedQuantityDecimal((string) $parentQuantity);
    }

    public function getExpectedQuantityDecimal(float|string $parentQuantity): string
    {
        $baseQuantity = DecimalMath::mul($parentQuantity, $this->quantity_per, DecimalPrecision::QUANTITY_SCALE);
        $scrapMultiplier = DecimalMath::add(
            '1',
            DecimalMath::div($this->scrap_percent ?? '0', '100', DecimalPrecision::CONVERSION_SCALE),
            DecimalPrecision::CONVERSION_SCALE
        );

        return DecimalMath::mul($baseQuantity, $scrapMultiplier, DecimalPrecision::QUANTITY_SCALE);
    }
}
