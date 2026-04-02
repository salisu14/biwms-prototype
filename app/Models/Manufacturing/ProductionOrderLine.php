<?php

namespace App\Models\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderLine extends Model
{
    use HasFactory;

    protected $table = 'production_order_lines';

    protected $fillable = [
        'production_order_id',
        'line_number',
        'item_id',
        'variant_code',
        'description',
        'quantity',
        'unit_of_measure_code',
        'quantity_base',
        'due_date',
        'starting_date_time',
        'ending_date_time',
        'production_bom_id',
        'routing_id',
        'location_code',
        'bin_code',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'unit_cost',
        'cost_amount',
        'finished',
        'finished_at',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'due_date' => 'date',
        'starting_date_time' => 'datetime',
        'ending_date_time' => 'datetime',
        'unit_cost' => 'decimal:4',
        'cost_amount' => 'decimal:4',
        'finished' => 'boolean',
        'finished_at' => 'datetime',
        'dimension_set_id' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function productionBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id');
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    // ==================== SCOPES ====================

    public function scopeForProductionOrder($query, int $productionOrderId)
    {
        return $query->where('production_order_id', $productionOrderId);
    }

    public function scopeFinished($query)
    {
        return $query->where('finished', true);
    }

    public function scopeUnfinished($query)
    {
        return $query->where('finished', false);
    }

    public function scopeDueBefore($query, $date)
    {
        return $query->where('due_date', '<=', $date);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getRemainingQuantityAttribute(): float
    {
        $produced = $this->productionOrder
            ?->itemLedgerEntries()
            ?->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ?->sum('quantity') ?? 0;

        return $this->quantity - $produced;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->remaining_quantity <= 0;
    }
}
