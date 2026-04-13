<?php

namespace App\Services\Overhead;

class OverheadBreakdown
{
    public function __construct(
        public readonly float $setupOverhead,    // Setup time portion
        public readonly float $runOverhead,     // Run time portion
        public readonly float $totalOverhead,    // Sum
        public readonly string $basis,           // 'hours', 'units', 'percentage'
        public readonly float $quantity,         // Hours or units
        public readonly float $rate,             // Applied rate
        public readonly ?float $fixedAllocation = null // If fixed cost spread
    ) {}

    public function perUnit(float $outputQuantity): float
    {
        return $this->totalOverhead / $outputQuantity;
    }
}
