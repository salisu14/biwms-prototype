<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use App\Enums\PayrollStatus;
use App\Enums\PayCodeType;
use App\Enums\CalculationMethod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('payroll:generate {--month=} {--year=}')]
#[Description('Generate a draft payroll document for all active employees based on active compensation rules.')]
class GeneratePayroll extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $month = $this->option('month') ?? date('m');
        $year = $this->option('year') ?? date('Y');

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $docNo = 'PRL-' . $start->format('Ym') . '-' . rand(100, 999);

        $this->info("Generating payroll draft {$docNo} for {$start->format('F Y')}...");

        DB::transaction(function () use ($docNo, $start, $end) {
            $doc = PayrollDocument::create([
                'document_number' => $docNo,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => PayrollStatus::DRAFT,
                'remarks' => "Auto-generated batch",
            ]);

            $employees = Employee::where('is_active', true)->get();

            // Find default PayCodes
            $baseSalaryCode = PayCode::where('code', 'BASE')->first();
            if (!$baseSalaryCode) {
                $this->error("A PayCode with code 'BASE' must exist to generate standard payroll.");
                return;
            }

            // Find all auto-applying codes (e.g. standard tax brackets with default amounts > 0)
            $autoCodes = PayCode::whereNotNull('default_amount')->where('default_amount', '>', 0)->get();

            foreach ($employees as $employee) {
                $salary = (float) $employee->getCurrentBaseSalary();
                if ($salary <= 0) {
                    continue; // Skip employees with no set salary
                }

                // 1. Add Base Salary
                PayrollLine::create([
                    'payroll_document_id' => $doc->id,
                    'employee_id' => $employee->id,
                    'pay_code_id' => $baseSalaryCode->id,
                    'amount' => $salary,
                    'description' => "Base Salary for {$start->format('M Y')}",
                ]);

                // 2. Apply Auto Deductions/Earnings
                foreach ($autoCodes as $code) {
                    $amount = 0;
                    if ($code->calculation_method === CalculationMethod::PERCENTAGE) {
                        $amount = $salary * ($code->default_amount / 100);
                    } else {
                        $amount = $code->default_amount;
                    }

                    if ($amount > 0) {
                        PayrollLine::create([
                            'payroll_document_id' => $doc->id,
                            'employee_id' => $employee->id,
                            'pay_code_id' => $code->id,
                            'amount' => round($amount, 2),
                            'description' => "Auto-applied: {$code->name}",
                        ]);
                    }
                }
            }
        });

        $this->info("Payroll document {$docNo} created successfully as DRAFT.");
    }
}
