<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingVersionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'routing_version_id',
        'line_number',
        'operation_no',
        'description',
        'work_center_id',
        'machine_center_id',
        'setup_time',
        'run_time',
        'wait_time',
        'move_time',
        'queue_time',
        'fixed_scrap_quantity',
        'setup_time_unit',
        'run_time_unit',
        'direct_unit_cost',
        'indirect_cost_percent',
        'overhead_rate',
        'scrap_factor_percent',
        'routing_link_code',
        'subcontractor_id',
        'subcontracting_cost',
        'concurrent_capacities',
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
        'subcontracting_cost' => 'decimal:4',
        'concurrent_capacities' => 'integer',
        'lot_size' => 'decimal:4',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(RoutingVersion::class, 'routing_version_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'subcontractor_id');
    }

    /**
     * Calculate total cost for this operation
     */
    public function calculateCost(float $quantity): array
    {
        $runTimeTotal = $this->run_time * ($quantity / ($this->lot_size ?: 1));

        $setupCost = $this->setup_time * $this->direct_unit_cost;
        $runCost = $runTimeTotal * $this->direct_unit_cost;
        $overhead = ($setupCost + $runCost) * ($this->indirect_cost_percent / 100);

        return [
            'setup_cost' => $setupCost,
            'run_cost' => $runCost,
            'overhead_cost' => $overhead,
            'total_cost' => $setupCost + $runCost + $overhead + $this->subcontracting_cost,
            'total_time' => $this->setup_time + $runTimeTotal + $this->wait_time + $this->move_time,
        ];
    }

    /**
     * Calculate expected scrap quantity
     */
    public function calculateScrap(float $quantity): float
    {
        $scrapPercent = $this->scrap_factor_percent / 100;
        return $this->fixed_scrap_quantity + ($quantity * $scrapPercent);
    }
}
