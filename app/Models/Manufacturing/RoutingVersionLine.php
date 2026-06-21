<?php

namespace App\Models\Manufacturing;

use App\Models\Vendor;
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
        return $this->belongsTo(Vendor::class, 'subcontractor_id');
    }

    /**
     * Calculate total cost for this operation
     */
    public function calculateCost(float $quantity): array
    {
        $rates = $this->resolveCenterRates();
        $lotSize = max((float) ($this->lot_size ?: 1), 1.0);
        $concurrentCapacities = max((int) ($this->concurrent_capacities ?: 1), 1);

        $setupTimeMinutes = $this->convertTimeToMinutes((float) $this->setup_time, (string) $this->setup_time_unit);
        $runTimeMinutesPerLot = $this->convertTimeToMinutes((float) $this->run_time, (string) $this->run_time_unit);
        $runTimeTotal = ($runTimeMinutesPerLot * ($quantity / $lotSize)) / $concurrentCapacities;
        $totalTimeMinutes = $setupTimeMinutes + $runTimeTotal + (float) $this->wait_time + (float) $this->move_time + (float) $this->queue_time;

        $setupCost = $setupTimeMinutes * $rates['direct_unit_cost'];
        $runCost = $runTimeTotal * $rates['direct_unit_cost'];
        $baseDirectCost = $setupCost + $runCost;
        $overhead = ($baseDirectCost * ($rates['indirect_cost_percent'] / 100)) + ($rates['overhead_rate'] * $totalTimeMinutes);

        return [
            'setup_cost' => $setupCost,
            'run_cost' => $runCost,
            'overhead_cost' => $overhead,
            'total_cost' => $baseDirectCost + $overhead + (float) $this->subcontracting_cost,
            'total_time' => $totalTimeMinutes,
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
        static::saving(function (RoutingVersionLine $line): void {
            $line->applyCenterCostRates();
        });
    }

    public function applyCenterCostRates(): void
    {
        $rates = $this->resolveCenterRates();

        $this->direct_unit_cost = $rates['direct_unit_cost'];
        $this->indirect_cost_percent = $rates['indirect_cost_percent'];
        $this->overhead_rate = $rates['overhead_rate'];
    }

    /**
     * @return array{direct_unit_cost: float, indirect_cost_percent: float, overhead_rate: float}
     */
    private function resolveCenterRates(): array
    {
        $machineCenter = $this->machine_center_id ? MachineCenter::find($this->machine_center_id) : null;
        $workCenter = $this->work_center_id ? WorkCenter::find($this->work_center_id) : null;
        $center = $machineCenter ?? $workCenter;

        if (! $center) {
            return [
                'direct_unit_cost' => (float) ($this->direct_unit_cost ?? 0),
                'indirect_cost_percent' => (float) ($this->indirect_cost_percent ?? 0),
                'overhead_rate' => (float) ($this->overhead_rate ?? 0),
            ];
        }

        return [
            'direct_unit_cost' => (float) ($center->direct_unit_cost ?? 0),
            'indirect_cost_percent' => (float) ($center->indirect_cost_percent ?? 0),
            'overhead_rate' => (float) ($center->overhead_rate ?? 0),
        ];
    }
}
