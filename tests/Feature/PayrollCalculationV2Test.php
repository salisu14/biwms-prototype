<?php

use App\Models\PayrollDocument;
use App\Models\Employee;
use App\Models\PayCode;
use App\Models\EmployeePayCode;
use App\Models\PayrollPeriod;
use App\Models\TaxTable;
use App\Models\TaxBracket;
use App\Models\SocialSecurityTier;
use App\Models\ChartOfAccount;
use App\Services\PayrollCalculationService;
use App\Services\TaxCalculationService;
use App\Enums\PayrollStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayCodeType;
use App\Enums\CalculationMethod;
use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed basic setup
    Artisan::call('db:seed', ['--class' => 'PayrollSetupV2Seeder']);
    
    // Create a GL Account for testing
    $account = ChartOfAccount::create([
        'account_number' => '6100',
        'name' => 'Salaries',
        'account_category' => AccountCategory::OPERATING_EXPENSE,
        'structural_type' => AccountStructuralType::POSTING,
    ]);

    // Create a BASE PayCode
    PayCode::create([
        'code' => 'BASE',
        'name' => 'Base Salary',
        'type' => PayCodeType::EARNING,
        'calculation_method' => CalculationMethod::PERCENTAGE,
        'taxable' => true,
        'gl_account_id' => $account->id,
    ]);

    // Ensure statutory codes exist
    PayCode::firstOrCreate(['code' => 'PAYE'], [
        'name' => 'PAYE', 
        'type' => PayCodeType::DEDUCTION, 
        'is_statutory' => true, 
        'gl_account_id' => $account->id,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
    ]);
    PayCode::firstOrCreate(['code' => 'NSSF'], [
        'name' => 'NSSF', 
        'type' => PayCodeType::DEDUCTION, 
        'is_statutory' => true, 
        'gl_account_id' => $account->id,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
    ]);
    PayCode::firstOrCreate(['code' => 'NHIF'], [
        'name' => 'NHIF', 
        'type' => PayCodeType::DEDUCTION, 
        'is_statutory' => true, 
        'gl_account_id' => $account->id,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
    ]);
});

it('calculates payroll correctly using advanced tax service', function () {
    // 1. Setup Employee with 50,000 salary
    $employee = Employee::factory()->create([
        'is_active' => true,
    ]);
    
    $employee->compensations()->create([
        'base_salary' => 50000,
        'effective_date' => now()->subMonth(),
    ]);

    // Give employee the BASE pay code at 100%
    $basePayCode = PayCode::where('code', 'BASE')->first();
    EmployeePayCode::create([
        'employee_id' => $employee->id,
        'pay_code_id' => $basePayCode->id,
        'percentage' => 100,
        'effective_date' => now()->subMonth(),
    ]);

    // 2. Setup Period and Document
    $period = PayrollPeriod::create([
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'payment_date' => now()->endOfMonth(),
        'status' => PayrollPeriodStatus::OPEN,
    ]);

    $document = PayrollDocument::create([
        'document_number' => 'PAY-001',
        'payroll_period_id' => $period->id,
        'period_start' => $period->start_date,
        'period_end' => $period->end_date,
        'status' => PayrollStatus::OPEN,
    ]);

    // 3. Trigger Calculation
    $service = app(PayrollCalculationService::class);
    $service->calculate($document);

    // 4. Verification
    $document->refresh();
    
    expect($document->status)->toBe(PayrollStatus::CALCULATED);
    expect($document->lines)->not->toBeEmpty();
    
    // Check Earnings line
    $baseLine = $document->lines()->where('pay_code_id', $basePayCode->id)->first();
    expect((float)$baseLine->amount)->toBe(50000.0);

    // Check for NSSF (6% of 50k but usually capped or tiered)
    // In our seeder/service: 
    // Tier 1: 6% of 7000 = 420
    // Tier 2: 6% of (36000-7000) = 1740
    // Total = 2160 (Our new service should handle this tiering better)
    $nssfLine = $document->lines()->whereHas('payCode', fn($q) => $q->where('code', 'NSSF'))->first();
    expect((float)$nssfLine->amount)->toBe(2160.0);

    // Check for NHIF (2.75% of 50k = 1375)
    $nhifLine = $document->lines()->whereHas('payCode', fn($q) => $q->where('code', 'NHIF'))->first();
    expect((float)$nhifLine->amount)->toBe(1375.0);

    // Check for PAYE (Progressive KE Tax)
    // Net Taxable = 50000 - 2160 = 47840
    // Bands:
    // 0-24000 @ 10% = 2400
    // 24000-32333 @ 25% = 2083.25
    // 32333-47840 @ 30% = (47840 - 32333) * 0.3 = 4652.1
    // Total = 9135.35 - Relief 2400 = 6735.35
    $payeLine = $document->lines()->whereHas('payCode', fn($q) => $q->where('code', 'PAYE'))->first();
    expect((float)$payeLine->amount)->toBe(6735.35);
});
