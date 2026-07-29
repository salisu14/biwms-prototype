<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductionVarianceType;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionVarianceCalculation extends Model
{
    protected $fillable = [
        'production_order_id',
        'production_expected_cost_snapshot_id',
        'variance_type',
        'cost_component',
        'item_id',
        'production_order_component_id',
        'production_order_routing_line_id',
        'capacity_ledger_entry_id',
        'expected_quantity',
        'actual_quantity',
        'expected_rate',
        'actual_rate',
        'expected_amount',
        'actual_amount',
        'variance_amount',
        'variance_reason',
        'calculation_identity',
        'settlement_identity',
        'posting_date',
        'original_source_date',
        'posted_value_entry_id',
        'reversal_of_variance_calculation_id',
        'cost_adjustment_batch_id',
        'metadata',
        'calculated_by',
        'calculated_at',
    ];

    protected $casts = [
        'variance_type' => ProductionVarianceType::class,
        'expected_quantity' => 'decimal:8',
        'actual_quantity' => 'decimal:8',
        'expected_rate' => 'decimal:8',
        'actual_rate' => 'decimal:8',
        'expected_amount' => 'decimal:4',
        'actual_amount' => 'decimal:4',
        'variance_amount' => 'decimal:4',
        'posting_date' => 'date',
        'original_source_date' => 'date',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function expectedCostSnapshot(): BelongsTo
    {
        return $this->belongsTo(ProductionExpectedCostSnapshot::class, 'production_expected_cost_snapshot_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderComponent::class, 'production_order_component_id');
    }

    public function routingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'production_order_routing_line_id');
    }

    public function capacityLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CapacityLedgerEntry::class);
    }

    public function postedValueEntry(): BelongsTo
    {
        return $this->belongsTo(ValueEntry::class, 'posted_value_entry_id');
    }
}
