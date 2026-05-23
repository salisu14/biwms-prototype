<?php

namespace App\Models\Manufacturing;

use App\Models\UnitOfMeasure;
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

    public function setupTimeUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'setup_time_unit', 'uom_code');
    }

    public function runTimeUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'run_time_unit', 'uom_code');
    }

    public function getTotalTimeMinutesAttribute(): float
    {
        $setup = $this->convertTimeToMinutes((float) $this->setup_time, (string) $this->setup_time_unit);
        $run = $this->convertTimeToMinutes((float) $this->run_time, (string) $this->run_time_unit);
        $wait = (float) $this->wait_time;
        $move = (float) $this->move_time;
        $queue = (float) $this->queue_time;

        return $setup + $run + $wait + $move + $queue;
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

    protected static function booted(): void
    {
        static::creating(function (RoutingLine $line): void {
            if (! empty($line->line_number)) {
                return;
            }

            $maxLineNumber = static::query()
                ->where('routing_id', $line->routing_id)
                ->max('line_number');

            $line->line_number = ((int) ($maxLineNumber ?? 0)) + 10000;
        });

        static::saving(function (RoutingLine $line): void {
            $line->normalizeNumericDefaults();
            $line->applyCenterCostRates();
        });
    }

    public function normalizeNumericDefaults(): void
    {
        $defaultZeroFields = [
            'setup_time',
            'run_time',
            'wait_time',
            'move_time',
            'queue_time',
            'fixed_scrap_quantity',
            'direct_unit_cost',
            'indirect_cost_percent',
            'overhead_rate',
            'scrap_factor_percent',
            'subcontracting_cost',
            'lot_size',
        ];

        foreach ($defaultZeroFields as $field) {
            if ($this->{$field} === null || $this->{$field} === '') {
                $this->{$field} = 0;
            }
        }

        if ($this->concurrent_capacities === null || $this->concurrent_capacities === '') {
            $this->concurrent_capacities = 1;
        }
    }

    public function applyCenterCostRates(): void
    {
        $machineCenter = $this->machine_center_id ? MachineCenter::find($this->machine_center_id) : null;
        $workCenter = $this->work_center_id ? WorkCenter::find($this->work_center_id) : null;
        $center = $machineCenter ?? $workCenter;

        if (! $center) {
            return;
        }

        $this->direct_unit_cost = (float) ($center->direct_unit_cost ?? 0);
        $this->indirect_cost_percent = (float) ($center->indirect_cost_percent ?? 0);
        $this->overhead_rate = (float) ($center->overhead_rate ?? 0);
    }
}
