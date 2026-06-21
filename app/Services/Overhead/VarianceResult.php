<?php

declare(strict_types=1);

namespace App\Services\Overhead;

/**
 * Immutable value object representing overhead variance calculation result
 */
readonly class VarianceResult
{
    public function __construct(
        public float $absorbed,
        public float $actual,
        public float $variance,
        public bool $isUnderAbsorbed,
        public ?float $absorptionRate = null,
        public ?float $efficiencyVariance = null,
        public ?float $spendingVariance = null
    ) {}

    /**
     * Factory method for simple variance calculation
     */
    public static function calculate(
        float $absorbed,
        float $actual
    ): self {
        $variance = $actual - $absorbed;

        return new self(
            absorbed: $absorbed,
            actual: $actual,
            variance: $variance,
            isUnderAbsorbed: $variance > 0,
            absorptionRate: $absorbed > 0 ? ($actual / $absorbed) * 100 : 0
        );
    }

    /**
     * Factory method with detailed variance analysis (Standard Costing)
     */
    public static function calculateDetailed(
        float $standardHoursAllowed,
        float $actualHours,
        float $standardRate,
        float $actualRate,
        float $actualOverhead
    ): self {
        // Spending Variance: (Actual Rate - Standard Rate) × Actual Hours
        $spendingVariance = ($actualRate - $standardRate) * $actualHours;

        // Efficiency Variance: (Actual Hours - Standard Hours) × Standard Rate
        $efficiencyVariance = ($actualHours - $standardHoursAllowed) * $standardRate;

        // Volume Variance (for fixed overhead): Budgeted - Applied
        $budgetedOverhead = $standardHoursAllowed * $standardRate;
        $appliedOverhead = $actualHours * $standardRate;
        $volumeVariance = $budgetedOverhead - $appliedOverhead;

        $totalVariance = $actualOverhead - $appliedOverhead;

        return new self(
            absorbed: $appliedOverhead,
            actual: $actualOverhead,
            variance: $totalVariance,
            isUnderAbsorbed: $totalVariance > 0,
            absorptionRate: $appliedOverhead > 0 ? ($actualOverhead / $appliedOverhead) * 100 : 0,
            efficiencyVariance: $efficiencyVariance,
            spendingVariance: $spendingVariance
        );
    }

    public function getAbsorptionPercentage(): float
    {
        return $this->absorptionRate ?? 0;
    }

    public function isSignificant(float $thresholdPercent = 5.0): bool
    {
        if ($this->absorbed == 0) {
            return false;
        }

        return abs($this->variance / $this->absorbed) * 100 > $thresholdPercent;
    }

    public function toArray(): array
    {
        return [
            'absorbed' => $this->absorbed,
            'actual' => $this->actual,
            'variance' => $this->variance,
            'is_under_absorbed' => $this->isUnderAbsorbed,
            'absorption_rate_percent' => $this->getAbsorptionPercentage(),
            'efficiency_variance' => $this->efficiencyVariance,
            'spending_variance' => $this->spendingVariance,
        ];
    }
}
