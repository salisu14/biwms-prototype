<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'quantity_per' => 'decimal:4',
        'scrap_percent' => 'decimal:2',
        'lead_time_offset_days' => 'integer',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductionBomVersion::class, 'production_bom_version_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Item::class);
    }

    public function relatedBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id_related');
    }

    /**
     * Calculate expected quantity including scrap
     */
    public function getExpectedQuantity(float $parentQuantity): float
    {
        $baseQty = $parentQuantity * $this->quantity_per;
        $scrapQty = $baseQty * ($this->scrap_percent / 100);

        return $baseQty + $scrapQty;
    }
}
