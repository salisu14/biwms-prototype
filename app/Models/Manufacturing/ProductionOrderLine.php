<?php

namespace App\Models\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_code', 'uom_code');
    }

    public function productionBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id');
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function routingLine(): HasOne
    {
        return $this->hasOne(ProductionOrderRoutingLine::class, 'production_order_id', 'production_order_id')
            ->orderBy('line_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class, 'inventory_posting_group_id');
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class, 'general_product_posting_group_id');
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

    public function getFinishedQuantityAttribute(): float
    {
        return $this->productionOrder
            ?->itemLedgerEntries()
            ?->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->where('item_id', $this->item_id)
            ?->sum('quantity') ?? 0;
    }

    public function getRemainingQuantityAttribute(): float
    {
        return $this->quantity - $this->finished_quantity;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->remaining_quantity <= 0;
    }

    protected static function booted(): void
    {
        static::creating(function (ProductionOrderLine $line): void {
            if (! $line->line_number) {
                $currentMaxLineNumber = static::query()
                    ->where('production_order_id', $line->production_order_id)
                    ->max('line_number');

                $line->line_number = ($currentMaxLineNumber ?? 0) + 10000;
            }

            if ($line->quantity_base === null) {
                $line->quantity_base = $line->quantity ?? 0;
            }

            $line->cost_amount = (float) ($line->quantity ?? 0) * (float) ($line->unit_cost ?? 0);

            if (! $line->created_by) {
                $line->created_by = auth()->id();
            }
        });

        static::updating(function (ProductionOrderLine $line): void {
            $line->last_modified_by = auth()->id();

            if ($line->isDirty('quantity') && ! $line->isDirty('quantity_base')) {
                $line->quantity_base = $line->quantity ?? 0;
            }

            if ($line->isDirty('quantity') || $line->isDirty('unit_cost')) {
                $line->cost_amount = (float) ($line->quantity ?? 0) * (float) ($line->unit_cost ?? 0);
            }
        });
    }
}
