<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\PayrollPostingGroup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('payroll:setup-prerequisites
    {--default-salary=250000 : Default base salary for employees without compensation}
    {--bank-code=000 : Default bank code for generated bank accounts}
    {--bank-name=Demo Bank : Default bank name for generated bank accounts}
    {--payment-method=Bank Transfer : Payment method for generated bank accounts}')]
#[Description('Backfill missing payroll prerequisites: posting group assignment, compensation, and primary bank accounts for active employees.')]
class SetupPayrollPrerequisites extends Command
{
    public function handle(): int
    {
        $defaultSalary = (float) $this->option('default-salary');
        $bankCode = (string) $this->option('bank-code');
        $bankName = (string) $this->option('bank-name');
        $paymentMethod = (string) $this->option('payment-method');

        $postingGroup = PayrollPostingGroup::query()->orderBy('id')->first();
        if (! $postingGroup) {
            $this->error('No Payroll Posting Group exists. Please create one first.');

            return self::FAILURE;
        }

        $assignedPostingGroups = 0;
        $createdCompensations = 0;
        $createdBankAccounts = 0;

        DB::transaction(function () use (
            $postingGroup,
            $defaultSalary,
            $bankCode,
            $bankName,
            $paymentMethod,
            &$assignedPostingGroups,
            &$createdCompensations,
            &$createdBankAccounts
        ): void {
            $employees = Employee::query()
                ->where('is_active', true)
                ->with(['bankAccounts', 'compensations'])
                ->get();

            foreach ($employees as $employee) {
                if (! $employee->payroll_posting_group_id) {
                    $employee->payroll_posting_group_id = $postingGroup->id;
                    $employee->save();
                    $assignedPostingGroups++;
                }

                $hasCompensation = $employee->compensations()
                    ->where('effective_date', '<=', now()->toDateString())
                    ->exists();

                if (! $hasCompensation) {
                    EmployeeCompensation::query()->create([
                        'employee_id' => $employee->id,
                        'effective_date' => now()->startOfMonth()->toDateString(),
                        'base_salary' => $defaultSalary,
                        'reason_code' => 'INITIAL_SETUP',
                        'audit_note' => 'Auto-created by payroll:setup-prerequisites',
                        'job_title' => $employee->job_title,
                    ]);
                    $createdCompensations++;
                }

                $hasPrimaryBank = $employee->bankAccounts()->where('is_primary', true)->exists();
                if (! $hasPrimaryBank) {
                    $employeeNumberDigits = preg_replace('/\D+/', '', (string) $employee->employee_number);
                    $accountSuffix = str_pad(substr((string) $employeeNumberDigits, -8), 8, '0', STR_PAD_LEFT);

                    $employee->bankAccounts()->create([
                        'bank_code' => $bankCode,
                        'bank_name' => $bankName,
                        'account_number' => "10{$accountSuffix}",
                        'account_name' => trim($employee->first_name.' '.$employee->last_name) ?: 'Employee',
                        'is_primary' => true,
                        'payment_method' => $paymentMethod,
                    ]);
                    $createdBankAccounts++;
                }
            }
        });

        $this->info('Payroll prerequisites setup completed.');
        $this->line("Assigned payroll posting groups: {$assignedPostingGroups}");
        $this->line("Created compensation records: {$createdCompensations}");
        $this->line("Created primary bank accounts: {$createdBankAccounts}");

        return self::SUCCESS;
    }
}
