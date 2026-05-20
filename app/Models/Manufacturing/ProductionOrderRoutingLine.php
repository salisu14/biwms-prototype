<?php

namespace App\Models\Manufacturing;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrderRoutingLine extends Model
{
    use HasFactory;

    protected $table = 'production_order_routing_lines';

    protected $fillable = [
        'production_order_id',
        'line_number',
        'operation_no',
        'description',

        // Centers
        'work_center_id',
        'machine_center_id',

        // Planned Times
        'setup_time',
        'run_time',
        'wait_time',
        'move_time',
        'setup_time_unit',
        'run_time_unit',

        // Actual Times
        'actual_setup_time',
        'actual_run_time',

        // Output
        'expected_output_quantity',
        'actual_output_quantity',
        'scrap_quantity',

        // Dates
        'starting_date_time',
        'ending_date_time',
        'actual_starting_date_time',
        'actual_ending_date_time',

        // Status
        'status', // PLANNED, IN_PROGRESS, COMPLETED

        // Routing Link
        'routing_link_code',

        // Costs
        'direct_cost',
        'overhead_cost',
        'total_cost',
    ];

    protected $casts = [
        'setup_time' => 'decimal:4',
        'run_time' => 'decimal:4',
        'wait_time' => 'decimal:4',
        'move_time' => 'decimal:4',
        'actual_setup_time' => 'decimal:4',
        'actual_run_time' => 'decimal:4',
        'expected_output_quantity' => 'decimal:4',
        'actual_output_quantity' => 'decimal:4',
        'scrap_quantity' => 'decimal:4',
        'starting_date_time' => 'datetime',
        'ending_date_time' => 'datetime',
        'actual_starting_date_time' => 'datetime',
        'actual_ending_date_time' => 'datetime',
        'direct_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'machine_center_id');
    }

    public function setupTimeUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'setup_time_unit', 'uom_code');
    }

    public function runTimeUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'run_time_unit', 'uom_code');
    }

    public function capacityLedgerEntries(): HasMany
    {
        return $this->hasMany(CapacityLedgerEntry::class, 'routing_line_id');
    }

    public function getTotalTimeMinutesAttribute(): float
    {
        $setup = $this->convertTimeToMinutes((float) $this->setup_time, (string) $this->setup_time_unit);
        $run = $this->convertTimeToMinutes((float) $this->run_time, (string) $this->run_time_unit);

        return $setup + $run + (float) $this->wait_time + (float) $this->move_time;
    }

    private function convertTimeToMinutes(float $time, string $unit): float
    {
        return match (strtoupper($unit)) {
            'HOURS', 'HOUR', 'HR', 'HRS' => $time * 60,
            'DAYS' => $time * 60 * 24,
            'MINUTES', 'MINUTE', 'MIN', 'MINS' => $time,
            default => $time,
        };
    }
}
