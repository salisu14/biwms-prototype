<?php

namespace App\Services;

use App\Models\TaxTable;
use App\Models\SocialSecurityTier;
use App\Models\PayrollPeriod;
use App\Models\Employee;
use App\Models\EmployeeYtdBalance;

class TaxCalculationService
{
    /**
     * Calculate tax using progressive tax brackets
     */
    public function calculateProgressiveTax($taxableIncome, $taxTableId, $ytdEarnings = 0)
    {
        $taxTable = TaxTable::findOrFail($taxTableId);
        $brackets = $taxTable->brackets()->orderBy('from_amount')->get();
        
        $totalTax = 0;
        $remainingIncome = $taxableIncome;
        $cumulativeIncome = $ytdEarnings; // For annual projections
        
        foreach ($brackets as $bracket) {
            if ($remainingIncome <= 0) break;
            
            // Calculate income falling within this bracket
            $bracketMin = $bracket->from_amount;
            $bracketMax = $bracket->to_amount ?? PHP_FLOAT_MAX;
            
            // Adjust for YTD if using annualized calculation
            $effectiveMin = max($bracketMin, $cumulativeIncome);
            $effectiveMax = $bracketMax;
            
            if ($effectiveMax <= $effectiveMin) continue;

            $incomeInBracket = min($remainingIncome - ($effectiveMin - $cumulativeIncome), $effectiveMax - $effectiveMin);
            
            // simplified logic to match progressive banding
            if ($taxableIncome > $bracketMin) {
                $taxableInBand = min($taxableIncome, $bracketMax) - $bracketMin;
                if ($taxableInBand > 0) {
                    $totalTax += ($taxableInBand * ($bracket->rate / 100));
                }
            }
        }
        
        return round($totalTax, 2);
    }
    
    /**
     * Calculate tax using flat rate with deductions
     */
    public function calculateFlatTax($grossAmount, $rate, $maxCap = null)
    {
        $taxableAmount = $grossAmount;
        
        if ($maxCap && $grossAmount > $maxCap) {
            $taxableAmount = $maxCap;
        }
        
        return round($taxableAmount * ($rate / 100), 2);
    }
    
    /**
     * Annualized tax calculation (projects annual income)
     */
    public function calculateAnnualizedTax(Employee $employee, $currentPeriodEarnings, PayrollPeriod $payrollPeriod, $taxTableId)
    {
        $periodsPerYear = 12; 
        
        $ytdBalance = EmployeeYtdBalance::where('employee_id', $employee->id)
            ->where('year', $payrollPeriod->start_date->year)
            ->first();

        $ytdEarnings = $ytdBalance?->gross_earnings ?? 0;
        $currentMonth = $payrollPeriod->start_date->month;
        
        $projectedAnnual = ($ytdEarnings + $currentPeriodEarnings) * ($periodsPerYear / $currentMonth);
        
        // Calculate annual tax
        $annualTax = $this->calculateProgressiveTax($projectedAnnual, $taxTableId, 0);
        
        // Calculate tax already paid YTD
        $ytdTaxPaid = $ytdBalance?->tax_deducted ?? 0;
        
        // Prorate for current period
        $projectedYtdTax = ($annualTax / $periodsPerYear) * $currentMonth;
        $currentPeriodTax = $projectedYtdTax - $ytdTaxPaid;
        
        return max(0, round($currentPeriodTax, 2));
    }
    
    /**
     * Social Security calculation (supports cumulative tiers/bands like NSSF)
     */
    public function calculateSocialSecurity($grossSalary, string $tierCode)
    {
        $tiers = SocialSecurityTier::where('tier_code', $tierCode)
            ->orderBy('from_salary', 'asc')
            ->get();
            
        if ($tiers->isEmpty()) {
            return ['employee' => 0, 'employer' => 0];
        }

        $totalEmployee = 0;
        $totalEmployer = 0;
        $appliedTierCode = $tierCode;

        foreach ($tiers as $tier) {
            if ($grossSalary <= $tier->from_salary) continue;

            $taxableAmount = min($grossSalary, $tier->to_salary ?? PHP_FLOAT_MAX) - $tier->from_salary;
            
            // Check for max_base override on the band
            if ($tier->max_base && $taxableAmount > $tier->max_base) {
                $taxableAmount = $tier->max_base;
            }

            if ($taxableAmount > 0) {
                $employeeAmt = $taxableAmount * ($tier->employee_rate / 100);
                $employerAmt = $taxableAmount * ($tier->employer_rate / 100);

                if ($tier->employee_max_amount) $employeeAmt = min($employeeAmt, $tier->employee_max_amount);
                if ($tier->employer_max_amount) $employerAmt = min($employerAmt, $tier->employer_max_amount);

                $totalEmployee += $employeeAmt;
                $totalEmployer += $employerAmt;
                $appliedTierCode = $tier->code ?? $tierCode;
            }
        }
        
        return [
            'employee' => round($totalEmployee, 2),
            'employer' => round($totalEmployer, 2),
            'tier_code' => $appliedTierCode
        ];
    }
    
    /**
     * Calculate taxable income with allowable deductions
     */
    public function calculateTaxableIncome($grossEarnings, array $preTaxDeductions = [])
    {
        $taxable = $grossEarnings;
        foreach ($preTaxDeductions as $deduction) {
            if (isset($deduction['reduces_taxable_income']) && $deduction['reduces_taxable_income']) {
                $taxable -= $deduction['amount'];
            }
        }
        return max(0, $taxable);
    }
}
