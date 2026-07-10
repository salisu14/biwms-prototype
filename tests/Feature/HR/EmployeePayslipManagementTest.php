<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\ChartOfAccount;
use App\Models\CompanyInformation;
use App\Models\Employee;
use App\Models\EmployeePayslip;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Hr\EmployeePayslipService;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);

    CompanyInformation::getInstance()
        ->forceFill([
            'company_name' => 'BIFLI Pilot Company',
            'address_line_1' => '1 Enterprise Way',
            'city' => 'Lagos',
            'phone_no' => '+2348000000000',
            'email' => 'payroll@example.test',
            'base_currency_code' => 'NGN',
        ])
        ->save();
});

it('generates immutable payslip snapshots only from approved or posted payroll results', function (): void {
    $user = payslipUserWithPermissions(['hr.employee_payslip.generate']);
    $this->actingAs($user);

    $draftDocument = payslipPayrollDocument(PayrollStatus::OPEN);
    payslipLines($draftDocument);

    expect(fn () => app(EmployeePayslipService::class)->generateForPayrollDocument($draftDocument))
        ->toThrow(Exception::class, 'approved or posted');

    $postedDocument = payslipPayrollDocument(PayrollStatus::POSTED);
    $employee = payslipLines($postedDocument);

    $payslips = app(EmployeePayslipService::class)->generateForPayrollDocument($postedDocument);
    $payslip = $payslips->sole();

    expect($payslip->employee_id)->toBe($employee->id)
        ->and($payslip->employee_name)->toBe($employee->fresh()->full_name)
        ->and((float) $payslip->gross_earnings)->toBe(5000.0)
        ->and((float) $payslip->total_deductions)->toBe(1000.0)
        ->and((float) $payslip->net_pay)->toBe(4000.0)
        ->and($payslip->earnings)->toHaveCount(1)
        ->and($payslip->deductions)->toHaveCount(1);

    $employee->update(['first_name' => 'Changed', 'last_name' => 'Name']);

    expect($payslip->fresh()->employee_name)->not->toBe($employee->fresh()->full_name);

    $this->assertDatabaseHas('audit_trails', [
        'event_type' => 'hr_payslip',
        'action' => 'payslip_generated',
        'auditable_type' => EmployeePayslip::class,
        'auditable_id' => $payslip->id,
    ]);

    $this->assertDatabaseHas('employee_payslip_histories', [
        'payslip_id' => $payslip->id,
        'event' => 'generated',
    ]);
});

it('is idempotent for the same employee payroll run', function (): void {
    $user = payslipUserWithPermissions(['hr.employee_payslip.generate']);
    $this->actingAs($user);

    $document = payslipPayrollDocument(PayrollStatus::POSTED);
    payslipLines($document);

    $first = app(EmployeePayslipService::class)->generateForPayrollDocument($document)->sole();
    $second = app(EmployeePayslipService::class)->generateForPayrollDocument($document)->sole();

    expect($second->id)->toBe($first->id)
        ->and(EmployeePayslip::query()->count())->toBe(1);
});

it('allows linked employees to view their own payslip only', function (): void {
    $hrUser = payslipUserWithPermissions(['hr.employee_payslip.generate']);
    $this->actingAs($hrUser);

    $document = payslipPayrollDocument(PayrollStatus::POSTED);
    $employee = payslipLines($document);
    $ownPayslip = app(EmployeePayslipService::class)->generateForPayrollDocument($document)->sole();

    $otherDocument = payslipPayrollDocument(PayrollStatus::POSTED);
    payslipLines($otherDocument, Employee::factory()->create());
    $otherPayslip = app(EmployeePayslipService::class)->generateForPayrollDocument($otherDocument)->sole();

    $employeeUser = User::factory()->create(['employee_id' => $employee->id]);

    $this->actingAs($employeeUser)
        ->get(route('employee-payslips.preview', $ownPayslip))
        ->assertSuccessful()
        ->assertSee($ownPayslip->payslip_number);

    $this->actingAs($employeeUser)
        ->get(route('employee-payslips.preview', $otherPayslip))
        ->assertNotFound();
});

it('blocks unauthorized users from downloading payslips', function (): void {
    $hrUser = payslipUserWithPermissions(['hr.employee_payslip.generate']);
    $this->actingAs($hrUser);

    $document = payslipPayrollDocument(PayrollStatus::POSTED);
    payslipLines($document);
    $payslip = app(EmployeePayslipService::class)->generateForPayrollDocument($document)->sole();

    $this->actingAs(User::factory()->create())
        ->get(route('employee-payslips.download', $payslip))
        ->assertNotFound();
});

it('downloads and prints the shared payslip layout for authorized users', function (): void {
    $user = payslipUserWithPermissions([
        'hr.employee_payslip.generate',
        'hr.employee_payslip.download',
        'hr.employee_payslip.print',
        'hr.employee_payslip.view',
    ]);
    $this->actingAs($user);

    $document = payslipPayrollDocument(PayrollStatus::POSTED);
    payslipLines($document);
    $payslip = app(EmployeePayslipService::class)->generateForPayrollDocument($document)->sole();

    $this->get(route('employee-payslips.print', $payslip))
        ->assertSuccessful()
        ->assertSee('Employee Payslip')
        ->assertSee($payslip->payslip_number);

    $this->get(route('employee-payslips.download', $payslip))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect((int) $payslip->fresh()->download_count)->toBe(1)
        ->and($payslip->fresh()->printed_at)->not->toBeNull();
});

it('revokes and regenerates through the service with audit history', function (): void {
    $user = payslipUserWithPermissions([
        'hr.employee_payslip.generate',
        'hr.employee_payslip.revoke',
        'hr.employee_payslip.regenerate',
    ]);
    $this->actingAs($user);

    $document = payslipPayrollDocument(PayrollStatus::POSTED);
    payslipLines($document);
    $payslip = app(EmployeePayslipService::class)->generateForPayrollDocument($document)->sole();

    app(EmployeePayslipService::class)->revoke($payslip, 'Incorrect bank cycle.');
    expect($payslip->fresh()->status)->toBe(EmployeePayslip::STATUS_REVOKED);

    app(EmployeePayslipService::class)->regenerate($payslip->fresh());
    expect($payslip->fresh()->status)->toBe(EmployeePayslip::STATUS_GENERATED);

    $this->assertDatabaseHas('employee_payslip_histories', [
        'payslip_id' => $payslip->id,
        'event' => 'revoked',
    ]);

    $this->assertDatabaseHas('employee_payslip_histories', [
        'payslip_id' => $payslip->id,
        'event' => 'regenerated',
    ]);
});

function payslipUserWithPermissions(array $permissions): User
{
    $role = Role::query()->create([
        'name' => 'payslip-test-role-'.str()->random(8),
        'guard_name' => 'web',
    ]);

    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function payslipPayrollDocument(PayrollStatus $status): PayrollDocument
{
    $period = PayrollPeriod::query()->create([
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'payment_date' => '2026-07-05',
        'status' => PayrollPeriodStatus::OPEN,
        'is_current' => true,
    ]);

    return PayrollDocument::query()->create([
        'document_number' => 'PAYSLIP-'.str()->upper(str()->random(6)),
        'payroll_period_id' => $period->id,
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'status' => $status,
    ]);
}

function payslipLines(PayrollDocument $document, ?Employee $employee = null): Employee
{
    $employee ??= Employee::factory()->create([
        'employee_number' => 'EMP-'.fake()->unique()->numerify('####'),
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'job_title' => 'Payroll Analyst',
        'department_code' => 'HR',
    ]);

    $earningCode = PayCode::query()->create([
        'code' => 'BASE-'.str()->upper(str()->random(4)),
        'name' => 'Base Salary',
        'type' => PayCodeType::EARNING,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
        'default_amount' => 5000,
        'taxable' => true,
        'gl_account_id' => ChartOfAccount::factory()->create(['account_category' => AccountCategory::OPERATING_EXPENSE])->id,
    ]);

    $deductionCode = PayCode::query()->create([
        'code' => 'PAYE-'.str()->upper(str()->random(4)),
        'name' => 'PAYE',
        'type' => PayCodeType::DEDUCTION,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
        'default_amount' => 1000,
        'is_statutory' => true,
        'gl_account_id' => ChartOfAccount::factory()->create(['account_category' => AccountCategory::LIABILITY])->id,
    ]);

    $document->lines()->create([
        'employee_id' => $employee->id,
        'pay_code_id' => $earningCode->id,
        'line_type' => PayCodeType::EARNING->getLabel(),
        'amount' => 5000,
        'description' => 'Base Salary',
    ]);

    $document->lines()->create([
        'employee_id' => $employee->id,
        'pay_code_id' => $deductionCode->id,
        'line_type' => PayCodeType::DEDUCTION->getLabel(),
        'amount' => 1000,
        'description' => 'PAYE',
    ]);

    return $employee;
}
