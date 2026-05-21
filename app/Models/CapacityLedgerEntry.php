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
        'routing_line_id',
        'work_center_id',
        'machine_center_id',
        'posting_date',
        'document_number',
        'setup_time',
        'run_time',
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
    ];

    protected $casts = [
        'posting_date' => 'date',
        'setup_time' => 'decimal:4',
        'run_time' => 'decimal:4',
        'stop_time' => 'decimal:4',
        'output_quantity' => 'decimal:4',
        'scrap_quantity' => 'decimal:4',
        'direct_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
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
}
