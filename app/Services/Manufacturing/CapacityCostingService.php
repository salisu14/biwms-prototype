<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\WorkCenter;

class CapacityCostingService
{
    /**
     * Calculate a suggested Direct Unit Cost for a Work Center based on linked resources.
     * Formula: (Labor Rate / Hour) + (Depreciation / Hour)
     */
    public function calculateSuggestedCost(WorkCenter|MachineCenter $capacityUnit): float
    {
        $laborRate = 0;
        $depreciationRate = 0;

        // 1. Labor Component
        if ($capacityUnit->operatorEmployee) {
            $laborRate = $this->calculateLaborRate($capacityUnit->operatorEmployee);
        }

        // 2. Asset Component
        if ($capacityUnit->fixedAsset) {
            $depreciationRate = $this->calculateDepreciationRate($capacityUnit->fixedAsset, $capacityUnit);
        }

        return $laborRate + $depreciationRate;
    }

    private function calculateLaborRate(Employee $employee): float
    {
        $monthlySalary = (float) $employee->getCurrentBaseSalary();

        // Standard assumption: 160 working hours per month
        $hourlyRate = $monthlySalary / 160;

        return $hourlyRate;
    }

    private function calculateDepreciationRate(FixedAsset $asset, WorkCenter|MachineCenter $unit): float
    {
        // Calculate monthly depreciation
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $monthlyDepreciation = $asset->calculateDepreciationForPeriod($start, $end);

        // Assuming unit's capacity is monthly or can be derived
        // For simplicity, we'll assume 160 capacity hours per month if not specified
        $capacityPerHour = (float) ($unit->capacity ?: 160);

        if ($capacityPerHour <= 0) {
            return 0;
        }

        return $monthlyDepreciation / $capacityPerHour;
    }
}
