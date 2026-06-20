<?php

namespace App\Models\Manufacturing;

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

        // Time
        'setup_time',
        'run_time',
        'setup_time_unit',
        'run_time_unit',

        // Costs
        'direct_cost',
        'overhead_cost',
        'total_cost',

        // Type
        'type', // SETUP, RUN, STOP, OUTPUT

        // Links
        'fixed_asset_id',
        'capex_project_id',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'setup_time' => 'decimal:4',
        'run_time' => 'decimal:4',
        'direct_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'fixed_asset_id' => 'integer',
        'capex_project_id' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function routingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'routing_line_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'machine_center_id');
    }
}
