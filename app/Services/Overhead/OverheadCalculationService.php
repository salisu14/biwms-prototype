<?php

namespace App\Services\Overhead;

use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\WorkCenter;

class OverheadCalculationService
{
    public function calculateForRoutingLine(
        ProductionOrderRoutingLine $routingLine,
        float $outputQuantity,
        float $actualRunTime,
        float $actualSetupTime
    ): OverheadBreakdown {

        $workCenter = $routingLine->workCenter;
        $machineCenter = $routingLine->machineCenter;

        // Determine which rate to use (hierarchy: Machine > Routing > Work Center)
        $rate = $this->resolveOverheadRate($routingLine, $workCenter, $machineCenter);

        // Calculate based on absorption method
        return match ($rate['method']) {
            'per_hour' => $this->calculateTimeBased(
                setupTime: $actualSetupTime,
                runTime: $actualRunTime,
                rate: $rate['amount']
            ),
            'per_unit' => $this->calculateUnitBased(
                quantity: $outputQuantity,
                rate: $rate['amount']
            ),
            'percentage' => $this->calculatePercentageBased(
                routingLine: $routingLine,
                percentage: $rate['amount']
            ),
        };
    }

    private function resolveOverheadRate(
        ProductionOrderRoutingLine $routingLine,
        WorkCenter $workCenter,
        ?MachineCenter $machineCenter
    ): array {
        // Priority 1: Machine Center specific rate
        if ($machineCenter && $machineCenter->overhead_rate > 0) {
            return [
                'method' => $machineCenter->overhead_rate_type,
                'amount' => $machineCenter->overhead_rate,
                'source' => 'machine_center',
            ];
        }

        // Priority 2: Routing Line override
        if ($routingLine->overhead_rate > 0) {
            return [
                'method' => 'per_hour', // or from routing
                'amount' => $routingLine->overhead_rate,
                'source' => 'routing_line',
            ];
        }

        // Priority 3: Work Center default
        return [
            'method' => $workCenter->overhead_rate_type,
            'amount' => $workCenter->overhead_rate,
            'source' => 'work_center',
        ];
    }

    private function calculateTimeBased(
        float $setupTime,
        float $runTime,
        float $rate
    ): OverheadBreakdown {
        $totalHours = $setupTime + $runTime;
        $amount = $totalHours * $rate;

        return new OverheadBreakdown(
            setupOverhead: $setupTime * $rate,
            runOverhead: $runTime * $rate,
            totalOverhead: $amount,
            basis: 'hours',
            quantity: $totalHours,
            rate: $rate
        );
    }
}
