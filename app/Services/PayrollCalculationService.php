<?php

namespace App\Services;

use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
use App\Enums\PayrollStatus;
use App\Models\AttendanceLedgerEntry;
use App\Models\Employee;
use App\Models\EmployeePayCode;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use App\Models\TaxTable;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PayrollCalculationService
{
    public function __construct(
        protected TaxCalculationService $taxService
    ) {}

    /**
     * Main entry point to calculate a payroll document.
     */
    public function calculate(PayrollDocument $document): void
    {
        Gate::authorize('calculate', $document);

        if ($document->status === PayrollStatus::POSTED) {
            throw new Exception('Cannot recalculate a posted document.');
        }

        if (! $document->payroll_period_id || ! $document->period) {
            throw new Exception('Payroll document must be linked to a payroll period before calculation.');
        }

        DB::transaction(function () use ($document) {
            // Respect user-selected employees already present on the document.
            // If none are seeded yet, fall back to all active employees.
            $selectedEmployeeIds = $document->lines()
                ->distinct()
                ->pluck('employee_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();

            // 1. Clear existing lines
            $document->lines()->delete();

            // 2. Fetch employees to calculate for this document
            $employees = Employee::query()
                ->with(['compensations', 'payrollPostingGroup'])
                ->where('is_active', true)
                ->whereNotNull('payroll_posting_group_id')
                ->whereHas('compensations', function ($query) use ($document) {
                    $query->where('effective_date', '<=', $document->period_end);
                })
                ->when($selectedEmployeeIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $selectedEmployeeIds))
                ->get();

            if ($employees->isEmpty()) {
                throw new Exception('No eligible employees found for this payroll document.');
            }

            foreach ($employees as $employee) {
                $this->calculateEmployee($document, $employee);
            }

            // 3. Update Document Totals
            $this->updateDocumentTotals($document);

            // 4. Update Status to CALCULATED
            $document->status = PayrollStatus::CALCULATED;
            $document->save();
        });
    }

    private function calculateEmployee(PayrollDocument $document, Employee $employee): void
    {
        if (! $employee->is_active || ! $employee->payroll_posting_group_id || $employee->getCurrentBaseSalary() <= 0) {
            throw new Exception("Employee {$employee->employee_number} is not eligible for payroll calculation.");
        }

        $taxableEarnings = 0;
        $totalEarnings = 0;
        $totalDeductions = 0;

        // 1. Process standard pay codes (BASE, ALLOWANCES)
        $payCodes = PayCode::all();

        foreach ($payCodes as $payCode) {
            $amount = $this->resolveAmount($employee, $payCode, $document);

            if ($amount <= 0 && ! $payCode->is_statutory) {
                continue;
            }

            // Create Line
            if ($amount > 0) {
                PayrollLine::create([
                    'payroll_document_id' => $document->id,
                    'employee_id' => $employee->id,
                    'pay_code_id' => $payCode->id,
                    'amount' => $amount,
                    'line_type' => $payCode->type->getLabel(),
                    'description' => "{$payCode->name} - calculated",
                ]);

                if ($payCode->type === PayCodeType::EARNING) {
                    $totalEarnings += $amount;
                    if ($payCode->taxable) {
                        $taxableEarnings += $amount;
                    }
                } elseif ($payCode->type === PayCodeType::DEDUCTION) {
                    $totalDeductions += $amount;
                }
            }
        }

        // 2. Calculate Statutory Deductions using TaxCalculationService

        // Social Security (e.g., NSSF)
        $ssResult = $this->taxService->calculateSocialSecurity($taxableEarnings, 'NSSF');
        $this->createStatutoryLine($document, $employee, 'NSSF', $ssResult['employee']);

        // Insurance (e.g., NHIF/SHIF)
        $insuranceResult = $this->taxService->calculateSocialSecurity($taxableEarnings, 'NHIF');
        $this->createStatutoryLine($document, $employee, 'NHIF', $insuranceResult['employee']);

        // Tax (PAYE)
        // Taxable income is usually Gross - Social Security (Employee portion)
        $netTaxable = $this->taxService->calculateTaxableIncome($taxableEarnings, [
            ['amount' => $ssResult['employee'], 'reduces_taxable_income' => true],
        ]);

        // Find active Tax Table for the jurisdiction
        $taxTable = TaxTable::where('jurisdiction', 'Kenya')
            ->where('effective_date', '<=', $document->period_start)
            ->first();

        if ($taxTable) {
            $taxAmount = $this->taxService->calculateProgressiveTax($netTaxable, $taxTable->id);

            // Apply Relief (Personal Relief for KE = 2400)
            $taxAmount = max(0, $taxAmount - 2400);

            $this->createStatutoryLine($document, $employee, 'PAYE', $taxAmount);
        }
    }

    private function resolveAmount(Employee $employee, PayCode $payCode, PayrollDocument $document): float
    {
        // 1. Check for Employee Overrides
        $override = EmployeePayCode::where('employee_id', $employee->id)
            ->where('pay_code_id', $payCode->id)
            ->where('effective_date', '<=', $document->period_end)
            ->where(function ($q) use ($document) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $document->period_start);
            })
            ->first();

        if ($override) {
            if ($override->amount) {
                return (float) $override->amount;
            }
            if ($override->percentage) {
                return $this->calculatePercentage($employee, $override->percentage);
            }
        }

        // 2. Check Global PayCode defaults
        if ($payCode->calculation_method === CalculationMethod::FIXED_AMOUNT) {
            return (float) $payCode->default_amount;
        }

        if ($payCode->calculation_method === CalculationMethod::PERCENTAGE) {
            return $this->calculatePercentage($employee, $payCode->default_percentage);
        }

        if ($payCode->calculation_method === CalculationMethod::HOURLY) {
            $hoursWorked = $this->getApprovedAttendanceHours($employee, $document);
            $fallbackHours = (float) ($document->working_days * 8);
            $hours = $hoursWorked > 0 ? $hoursWorked : $fallbackHours;

            return (float) (($payCode->default_amount ?? 0) * $hours);
        }

        return 0;
    }

    private function getApprovedAttendanceHours(Employee $employee, PayrollDocument $document): float
    {
        return (float) AttendanceLedgerEntry::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'APPROVED')
            ->whereBetween('attendance_date', [$document->period_start, $document->period_end])
            ->sum('worked_hours');
    }

    private function calculatePercentage(Employee $employee, float $percentage): float
    {
        return (float) ($employee->getCurrentBaseSalary() * ($percentage / 100));
    }

    private function createStatutoryLine(PayrollDocument $document, Employee $employee, string $code, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $payCode = PayCode::where('code', $code)->first();
        if (! $payCode) {
            return;
        }

        PayrollLine::create([
            'payroll_document_id' => $document->id,
            'employee_id' => $employee->id,
            'pay_code_id' => $payCode->id,
            'amount' => $amount,
            'line_type' => $payCode->type->getLabel(),
            'description' => "Statutory Deduction: {$code}",
        ]);
    }

    public function updateDocumentTotals(PayrollDocument $document): void
    {
        $document->total_earnings = $document->lines()->where('line_type', PayCodeType::EARNING->getLabel())->sum('amount');
        $document->total_deductions = $document->lines()->where('line_type', PayCodeType::DEDUCTION->getLabel())->sum('amount');
        $document->total_net_pay = $document->total_earnings - $document->total_deductions;
        $document->save();
    }
}
