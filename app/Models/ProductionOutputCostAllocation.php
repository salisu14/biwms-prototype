<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductionOutputAllocationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOutputCostAllocation extends Model
{
    protected $fillable = [
        'production_order_id',
        'output_item_ledger_entry_id',
        'output_value_entry_id',
        'output_quantity',
        'eligible_cost_before_allocation',
        'allocated_material_cost',
        'allocated_capacity_cost',
        'allocated_overhead_cost',
        'allocated_total_cost',
        'allocation_status',
        'is_final_allocation',
        'finalized_at',
        'reversed_at',
        'reversed_allocation_id',
        'idempotency_key',
        'source_identity_key',
        'metadata',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:8',
        'eligible_cost_before_allocation' => 'decimal:4',
        'allocated_material_cost' => 'decimal:4',
        'allocated_capacity_cost' => 'decimal:4',
        'allocated_overhead_cost' => 'decimal:4',
        'allocated_total_cost' => 'decimal:4',
        'allocation_status' => ProductionOutputAllocationStatus::class,
        'is_final_allocation' => 'boolean',
        'finalized_at' => 'datetime',
        'reversed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(Manufacturing\ProductionOrder::class);
    }

    public function outputItemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class, 'output_item_ledger_entry_id');
    }

    public function outputValueEntry(): BelongsTo
    {
        return $this->belongsTo(ValueEntry::class, 'output_value_entry_id');
    }

    public function reversedAllocation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_allocation_id');
    }
}
