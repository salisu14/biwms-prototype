<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingLine extends Model
{
    use HasFactory;

    protected $table = 'routing_lines';

    protected $fillable = [
        'routing_id',
        'line_number',
        'operation_no',
        'description',

        // Work Centers
        'work_center_id',
        'machine_center_id',

        // Times
        'setup_time',
        'run_time',
        'wait_time',
        'move_time',
        'queue_time',
        'fixed_scrap_quantity',

        // Time Units
        'setup_time_unit', // MINUTES, HOURS, DAYS
        'run_time_unit',

        // Costing
        'direct_unit_cost',
        'indirect_cost_percent',
        'overhead_rate',

        // Scrap
        'scrap_factor_percent',

        // Routing Link Code (connects to BOM components)
        'routing_link_code',

        // Subcontracting
        'subcontractor_id', // Vendor ID if outsourced
        'subcontracting_cost',

        // Concurrent capacity
        'concurrent_capacities',

        // Lot size
        'lot_size',
    ];

    protected $casts = [
        'setup_time' => 'decimal:4',
        'run_time' => 'decimal:4',
        'wait_time' => 'decimal:4',
        'move_time' => 'decimal:4',
        'queue_time' => 'decimal:4',
        'fixed_scrap_quantity' => 'decimal:4',
        'direct_unit_cost' => 'decimal:4',
        'indirect_cost_percent' => 'decimal:2',
        'overhead_rate' => 'decimal:4',
        'scrap_factor_percent' => 'decimal:2',
        'concurrent_capacities' => 'integer',
        'lot_size' => 'decimal:4',
    ];

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'machine_center_id');
    }

    public function getTotalTimeMinutesAttribute(): float
    {
        $setup = $this->setup_time * ($this->setup_time_unit === 'HOURS' ? 60 : 1);
        $run = $this->run_time * ($this->run_time_unit === 'HOURS' ? 60 : 1);
        $wait = $this->wait_time;
        $queue = $this->queue_time;

        return $setup + $run + $wait + $queue;
    }
}
