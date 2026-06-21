<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\DepreciationMethod;
use App\Models\FixedAsset;
use Carbon\Carbon;

class DepreciationCalculationService
{
    public function calculate(
        FixedAsset $asset,
        \DateTime $from,
        \DateTime $to
    ): float {
        if (! $asset->canDepreciate()) {
            return 0;
        }

        return match ($asset->depreciation_method) {
            DepreciationMethod::STRAIGHT_LINE => $this->calculateStraightLine($asset, $from, $to),
            DepreciationMethod::DECLINING_BALANCE => $this->calculateDecliningBalance($asset, $from, $to),
            DepreciationMethod::DOUBLE_DECLINING => $this->calculateDoubleDeclining($asset, $from, $to),
            DepreciationMethod::UNITS_OF_PRODUCTION => $this->calculateUnitsOfProduction($asset, $from, $to),
            DepreciationMethod::SUM_OF_YEARS_DIGITS => $this->calculateSumOfYearsDigits($asset, $from, $to),
            DepreciationMethod::MANUAL => 0, // User enters manually
            DepreciationMethod::NONE => 0,
        };
    }

    private function calculateStraightLine(FixedAsset $asset, \DateTime $from, \DateTime $to): float
    {
        $depreciableBase = (float) $asset->acquisition_cost - (float) $asset->salvage_value;

        if ($asset->useful_life_years <= 0) {
            return 0;
        }

        // BC Standard: 360 days per year (30 days per month)
        $daysInYear = 360;
        $annualDepreciation = $depreciableBase / $asset->useful_life_years;

        // Daily rate for partial periods
        $daysInPeriod = $this->calculateDaysBetween($from, $to);
        $dailyDepreciation = $annualDepreciation / $daysInYear;

        return round($dailyDepreciation * $daysInPeriod, 2);
    }

    private function calculateDecliningBalance(FixedAsset $asset, \DateTime $from, \DateTime $to): float
    {
        $rate = (float) $asset->depreciation_rate / 100;
        $bookValue = (float) $asset->book_value;

        $annualDepreciation = $bookValue * $rate;
        $daysInYear = 360;

        $daysInPeriod = $this->calculateDaysBetween($from, $to);

        return round(($annualDepreciation / $daysInYear) * $daysInPeriod, 2);
    }

    private function calculateDaysBetween(\DateTime $from, \DateTime $to): int
    {
        $fromCarbon = Carbon::parse($from);
        $toCarbon = Carbon::parse($to);

        // 30/360 day count convention (BC Standard)
        $d1 = $fromCarbon->day;
        $m1 = $fromCarbon->month;
        $y1 = $fromCarbon->year;

        $d2 = $toCarbon->day;
        $m2 = $toCarbon->month;
        $y2 = $toCarbon->year;

        if ($d1 === 31) {
            $d1 = 30;
        }
        if ($d2 === 31 && $d1 === 30) {
            $d2 = 30;
        }

        return ($y2 - $y1) * 360 + ($m2 - $m1) * 30 + ($d2 - $d1) + 1;
    }

    private function calculateDoubleDeclining(FixedAsset $asset, \DateTime $from, \DateTime $to): float
    {
        // 2 × straight-line rate applied to book value
        $straightLineRate = 100 / $asset->useful_life_years; // % per year
        $doubleRate = ($straightLineRate * 2) / 100; // As decimal

        $bookValue = $asset->net_book_value;
        $annualDepreciation = $bookValue * $doubleRate;

        // Don't depreciate below salvage value
        $maxDepreciation = $bookValue - $asset->salvage_value;
        $annualDepreciation = min($annualDepreciation, $maxDepreciation);

        $daysInPeriod = $from->diff($to)->days + 1;

        return round(($annualDepreciation / 365) * $daysInPeriod, 2);
    }

    private function calculateUnitsOfProduction(FixedAsset $asset, \DateTime $from, \DateTime $to): float
    {
        if (! $asset->total_estimated_units) {
            return 0;
        }

        // Get units produced in period (from production data)
        $unitsProduced = $this->getUnitsProduced($asset, $from, $to);

        $depreciableBase = $asset->book_value - $asset->salvage_value;
        $ratePerUnit = $depreciableBase / $asset->total_estimated_units;

        return round($unitsProduced * $ratePerUnit, 2);
    }

    private function calculateSumOfYearsDigits(FixedAsset $asset, \DateTime $from, \DateTime $to): float
    {
        $remainingLife = $asset->remaining_life_months;
        $totalLife = $asset->useful_life_years * 12;

        if ($remainingLife <= 0 || $totalLife <= 0) {
            return 0;
        }

        // Sum of years digits: n(n+1)/2 where n = useful life in years
        $sumOfYears = ($asset->useful_life_years * ($asset->useful_life_years + 1)) / 2;
        $currentYearDigit = ceil($remainingLife / 12);

        $depreciableBase = $asset->book_value - $asset->salvage_value;
        $annualDepreciation = $depreciableBase * ($currentYearDigit / $sumOfYears);

        $daysInPeriod = $from->diff($to)->days + 1;

        return round(($annualDepreciation / 365) * $daysInPeriod, 2);
    }

    private function getUnitsProduced(FixedAsset $asset, \DateTime $from, \DateTime $to): float
    {
        // Query production output linked to this asset (machine center)
        // Implementation depends on your production data structure
        return 0; // Placeholder
    }

    public function calculateForBatch(
        array $assetIds,
        \DateTime $postingDate,
        bool $useForceNoOfDays = false,
        ?int $forcedDays = null
    ): array {
        $results = [];

        foreach ($assetIds as $assetId) {
            $asset = FixedAsset::find($assetId);
            if (! $asset || ! $asset->canDepreciate()) {
                continue;
            }

            $from = Carbon::parse($asset->depreciation_starting_date)->startOfMonth();
            $to = Carbon::parse($postingDate);

            $days = $useForceNoOfDays ? $forcedDays : $from->diffInDays($to) + 1;

            $amount = $this->calculate($asset, $from->toDateTime(), $to->toDateTime());

            // Adjust for partial month if needed
            if ($asset->posting_group->depreciation_calculation === 'half_year') {
                $amount = $this->applyHalfYearConvention($amount, $asset, $postingDate);
            }

            $results[] = [
                'fixed_asset_id' => $asset->id,
                'fa_no' => $asset->fa_no,
                'description' => $asset->description,
                'amount' => $amount,
                'days' => $days,
                'book_value_before' => $asset->net_book_value,
                'book_value_after' => $asset->net_book_value - $amount,
            ];
        }

        return $results;
    }

    private function applyHalfYearConvention(float $amount, FixedAsset $asset, \DateTime $date): float
    {
        // Half-year convention: regardless of acquisition date, first year = 50% of annual depreciation
        $acquisitionYear = Carbon::parse($asset->acquisition_date)->year;
        $currentYear = Carbon::parse($date)->year;

        if ($acquisitionYear === $currentYear) {
            return $amount * 0.5; // Half in first year
        }

        return $amount;
    }
}
