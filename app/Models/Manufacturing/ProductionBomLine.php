<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Item;

class ProductionBomLine extends Model
{
    use HasFactory;

    protected $table = 'production_bom_lines';

    // Line Types
    const TYPE_ITEM = 'ITEM';
    const TYPE_PRODUCTION_BOM = 'PRODUCTION_BOM';

    protected $fillable = [
        'production_bom_id',
        'line_number',
        'type', // ITEM, PRODUCTION_BOM
        'item_id',
        'production_bom_id_related', // For sub-BOMs
        'description',
        'unit_of_measure_code',
        'quantity_per',
        'scrap_percent',

        // Routing integration
        'routing_link_code', // Links to routing operation

        // Flushing
        'flushing_method', // MANUAL, FORWARD, BACKWARD

        // Position
        'position',
        'position_2',
        'position_3',

        // Lead time
        'lead_time_offset_days',

        // Location
        'location_code',
        'bin_code',
    ];

    protected $casts = [
        'quantity_per' => 'decimal:4',
        'scrap_percent' => 'decimal:2',
        'lead_time_offset_days' => 'integer',
    ];

    public function productionBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function relatedBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id_related');
    }
}
