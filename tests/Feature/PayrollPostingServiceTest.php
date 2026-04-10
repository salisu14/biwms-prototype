<?php

use App\Models\Employee;
use App\Models\EmployeePostingGroup;
use App\Models\ChartOfAccount;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use App\Models\GlEntry;
use App\Enums\PayCodeType;
use App\Enums\CalculationMethod;
use App\Enums\PayrollStatus;
use App\Services\PayrollPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('posting payroll calculates balanced gl entries for earning and deduction', function () {
    // Setup Admin
    \App\Models\User::factory()->create();

    // Setup Accounting
    $salaryExpenseAccount = ChartOfAccount::factory()->create(['account_type' => 'EXPENSE']);
    $salaryPayableAccount = ChartOfAccount::factory()->create(['account_type' => 'LIABILITY']);
    $taxPayableAccount = ChartOfAccount::factory()->create(['account_type' => 'LIABILITY']);

    // Setup Employee
    $postingGroup = EmployeePostingGroup::factory()->create(['payables_account_id' => $salaryPayableAccount->id]);
    $employee = Employee::factory()->create(['employee_posting_group_id' => $postingGroup->id]);

    // Setup PayCodes
    $salaryCode = PayCode::create([
        'code' => 'BASE',
        'name' => 'Base Salary',
        'type' => PayCodeType::EARNING,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
        'gl_account_id' => $salaryExpenseAccount->id,
    ]);

    $taxCode = PayCode::create([
        'code' => 'TAX',
        'name' => 'Income Tax',
        'type' => PayCodeType::DEDUCTION,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
        'gl_account_id' => $taxPayableAccount->id,
    ]);

    // Setup Payroll Document
    $doc = PayrollDocument::create([
        'document_number' => 'PRL-001',
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'status' => PayrollStatus::DRAFT,
    ]);

    PayrollLine::create([
        'payroll_document_id' => $doc->id,
        'employee_id' => $employee->id,
        'pay_code_id' => $salaryCode->id,
        'amount' => 5000,
    ]);

    PayrollLine::create([
        'payroll_document_id' => $doc->id,
        'employee_id' => $employee->id,
        'pay_code_id' => $taxCode->id,
        'amount' => 1000,
    ]);

    // Action
    $service = new PayrollPostingService();
    $service->post($doc);

    // Assert Status is Posted
    expect($doc->fresh()->status)->toBe(PayrollStatus::POSTED);

    // Earning should Debit Expense (5000) and Credit Payable (-5000)
    $salaryExpenseEntry = GlEntry::where('chart_of_account_id', $salaryExpenseAccount->id)->first();
    expect((float) $salaryExpenseEntry->amount)->toBe(5000.00);

    // Deduction should Debit Payable (1000) and Credit Tax Liability (-1000)
    $taxLiabilityEntry = GlEntry::where('chart_of_account_id', $taxPayableAccount->id)->first();
    expect((float) $taxLiabilityEntry->amount)->toBe(-1000.00);

    // Total Salaries Payable net should be -4000 (Cr 5000 + Dr 1000)
    $netPayable = GlEntry::where('chart_of_account_id', $salaryPayableAccount->id)->sum('amount');
    expect((float) $netPayable)->toBe(-4000.00);
});
