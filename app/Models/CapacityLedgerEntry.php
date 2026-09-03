<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapacityLedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'capacity_ledger_entries';

    protected $fillable = [
        'production_order_id',
        'business_id',
        'routing_line_id',
        'work_center_id',
        'machine_center_id',
        'posting_date',
        'document_number',
        'setup_time',
        'run_time',
        'wait_time',
        'queue_time',
        'stop_time',
        'setup_time_unit',
        'run_time_unit',
        'output_quantity',
        'scrap_quantity',
        'direct_cost',
        'overhead_cost',
        'unit_cost',
        'total_cost',
        'type',
        'cost_state',
        'idempotency_key',
        'reversal_of_capacity_ledger_entry_id',
        'costing_metadata',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'posting_date' => 'date',
        'setup_time' => 'decimal:4',
        'run_time' => 'decimal:4',
        'wait_time' => 'decimal:8',
        'queue_time' => 'decimal:8',
        'stop_time' => 'decimal:4',
        'output_quantity' => 'decimal:4',
        'scrap_quantity' => 'decimal:4',
        'direct_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'costing_metadata' => 'array',
    ];

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            if ($entry->production_order_id && $entry->business_id === null) {
                $entry->business_id = $entry->productionOrder()->value('business_id');
            }
        });

        static::saving(function (self $entry): void {
            if ($entry->production_order_id && $entry->business_id !== null) {
                $orderBusinessId = $entry->productionOrder()->value('business_id');
                if ($orderBusinessId !== null && (int) $orderBusinessId !== (int) $entry->business_id) {
                    throw new \InvalidArgumentException('Capacity ledger entry business does not match its production order.');
                }
            }
        });
    }

    /**
     * @return BelongsTo<ProductionOrderRoutingLine, $this>
     */
    public function routingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'routing_line_id');
    }

    /**
     * @return BelongsTo<WorkCenter, $this>
     */
    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    /**
     * @return BelongsTo<MachineCenter, $this>
     */
    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function reversalOfCapacityLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_capacity_ledger_entry_id');
    }
}
