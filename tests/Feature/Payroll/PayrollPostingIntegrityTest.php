<?php

use App\Enums\AccountCategory;
use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Events\PayrollPosted;
use App\Events\PayrollSalaryPaid;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollPeriod;
use App\Models\PayrollPostingGroup;
use App\Models\Permission;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PayrollPaymentService;
use App\Services\PayrollPostingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('payroll calculation includes only active employees with complete payroll setup', function () {
    $fixture = payrollFixture();
    grantPayrollPermission($fixture['user'], 'payroll.calculate');
    $this->actingAs($fixture['user']);

    $eligible = createPayrollEmployee($fixture['postingGroup'], true, 5000);
    createPayrollEmployee($fixture['postingGroup'], false, 5000);
    createPayrollEmployee($fixture['postingGroup'], true, null);

    $document = payrollDocument(PayrollStatus::OPEN);

    app(PayrollCalculationService::class)->calculate($document);

    expect($document->fresh()->status)->toBe(PayrollStatus::CALCULATED)
        ->and($document->lines()->distinct('employee_id')->count('employee_id'))->toBe(1)
        ->and($document->lines()->where('employee_id', $eligible->id)->exists())->toBeTrue();
});

test('payroll posting creates payable and balanced gl without touching bank', function () {
    Event::fake([PayrollPosted::class]);

    $fixture = payrollFixture();
    grantPayrollPermission($fixture['user'], 'payroll.post');
    $this->actingAs($fixture['user']);

    $employee = createPayrollEmployee($fixture['postingGroup'], true, 5000);
    $document = payrollDocument(PayrollStatus::CALCULATED);
    payrollLines($document, $employee, $fixture['salaryCode'], $fixture['taxCode']);
    $bankLedgerCount = BankAccountLedgerEntry::query()->count();

    app(PayrollPostingService::class)->post($document);

    $glEntries = GlEntry::query()->where('document_number', $document->document_number)->get();

    expect($document->fresh()->status)->toBe(PayrollStatus::POSTED)
        ->and(round((float) $glEntries->sum('debit_amount'), 2))->toBe(round((float) $glEntries->sum('credit_amount'), 2))
        ->and((float) GlEntry::query()->where('chart_of_account_id', $fixture['payableAccount']->id)->sum('amount'))->toBe(-4000.0)
        ->and(BankAccountLedgerEntry::query()->count())->toBe($bankLedgerCount)
        ->and($document->lines()->where('posted_to_g_l', true)->count())->toBe(2);

    Event::assertDispatched(PayrollPosted::class);

    expect(fn () => app(PayrollPostingService::class)->post($document->fresh()))
        ->toThrow(Exception::class, 'already posted');
});

test('salary payment clears payable, credits bank, creates bank ledger, and blocks duplicates', function () {
    Event::fake([PayrollSalaryPaid::class]);

    $fixture = payrollFixture();
    grantPayrollPermission($fixture['user'], 'payroll.post');
    grantPayrollPermission($fixture['user'], 'payroll.pay');
    $this->actingAs($fixture['user']);

    $employee = createPayrollEmployee($fixture['postingGroup'], true, 5000);
    EmployeeBankAccount::query()->create([
        'employee_id' => $employee->id,
        'bank_code' => 'BANK',
        'bank_name' => 'Payroll Bank',
        'account_number' => '1234567890',
        'account_name' => "{$employee->first_name} {$employee->last_name}",
        'is_primary' => true,
        'payment_method' => 'Bank Transfer',
    ]);

    $document = payrollDocument(PayrollStatus::CALCULATED);
    payrollLines($document, $employee, $fixture['salaryCode'], $fixture['taxCode']);
    app(PayrollPostingService::class)->post($document);

    $bankAccount = BankAccount::factory()->paymentOnly()->create([
        'gl_account_id' => $fixture['bankAccount']->id,
        'current_balance' => 10000,
        'available_balance' => 10000,
    ]);

    $bankLedgerEntry = app(PayrollPaymentService::class)->pay($document->fresh(), $bankAccount, $fixture['user']->id);

    expect((float) $bankLedgerEntry->amount)->toBe(-4000.0)
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(6000.0)
        ->and(BankAccountLedgerEntry::query()->where('document_no', "{$document->document_number}-PAY")->exists())->toBeTrue();

    $paymentEntries = GlEntry::query()->where('document_number', "{$document->document_number}-PAY")->get();
    expect(round((float) $paymentEntries->sum('debit_amount'), 2))->toBe(4000.0)
        ->and(round((float) $paymentEntries->sum('credit_amount'), 2))->toBe(4000.0);

    Event::assertDispatched(PayrollSalaryPaid::class);

    expect(fn () => app(PayrollPaymentService::class)->pay($document->fresh(), $bankAccount->fresh(), $fixture['user']->id))
        ->toThrow(Exception::class, 'already posted');
});

test('payroll posting and payment enforce authorization and payment bank setup', function () {
    $fixture = payrollFixture();
    $this->actingAs($fixture['user']);

    $employee = createPayrollEmployee($fixture['postingGroup'], true, 5000);
    $document = payrollDocument(PayrollStatus::CALCULATED);
    payrollLines($document, $employee, $fixture['salaryCode'], $fixture['taxCode']);

    expect(fn () => app(PayrollPostingService::class)->post($document))
        ->toThrow(AuthorizationException::class);

    grantPayrollPermission($fixture['user'], 'payroll.post');
    grantPayrollPermission($fixture['user'], 'payroll.pay');
    app(PayrollPostingService::class)->post($document);

    $bankAccount = BankAccount::factory()->paymentOnly()->create([
        'gl_account_id' => $fixture['bankAccount']->id,
        'current_balance' => 10000,
        'available_balance' => 10000,
    ]);

    expect(fn () => app(PayrollPaymentService::class)->pay($document->fresh(), $bankAccount, $fixture['user']->id))
        ->toThrow(Exception::class, 'no primary bank account');
});

function payrollFixture(): array
{
    ensureOpenPostingPeriod();
    ensurePayrollBankLedgerNumberSeries();

    $user = User::factory()->create();
    $salaryExpense = ChartOfAccount::factory()->create(['account_category' => AccountCategory::OPERATING_EXPENSE]);
    $payableAccount = ChartOfAccount::factory()->create(['account_category' => AccountCategory::LIABILITY]);
    $taxPayable = ChartOfAccount::factory()->create(['account_category' => AccountCategory::LIABILITY]);
    $bankAccount = ChartOfAccount::factory()->create(['account_category' => AccountCategory::ASSET]);

    $postingGroup = PayrollPostingGroup::query()->create([
        'code' => 'PAY',
        'description' => 'Payroll',
        'salaries_account_id' => $salaryExpense->id,
        'social_security_account_id' => $taxPayable->id,
        'tax_payable_account_id' => $taxPayable->id,
        'net_pay_account_id' => $payableAccount->id,
    ]);

    $salaryCode = PayCode::query()->create([
        'code' => 'BASE',
        'name' => 'Base Salary',
        'type' => PayCodeType::EARNING,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
        'default_amount' => 5000,
        'taxable' => true,
        'gl_account_id' => $salaryExpense->id,
    ]);

    $taxCode = PayCode::query()->create([
        'code' => 'PAYE',
        'name' => 'PAYE',
        'type' => PayCodeType::DEDUCTION,
        'calculation_method' => CalculationMethod::FIXED_AMOUNT,
        'default_amount' => 1000,
        'is_statutory' => true,
        'gl_account_id' => $taxPayable->id,
    ]);

    return compact('user', 'postingGroup', 'salaryCode', 'taxCode', 'payableAccount', 'bankAccount');
}

function ensurePayrollBankLedgerNumberSeries(): void
{
    $series = NumberSeries::query()->firstOrCreate(
        ['code' => 'BANK-LEDGER'],
        [
            'description' => 'Bank Ledger Entries',
            'prefix' => '',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'finance',
        ]
    );

    NumberSeriesLine::query()->firstOrCreate(
        ['number_series_id' => $series->id, 'starting_date' => '2026-01-01'],
        [
            'prefix' => '',
            'suffix' => '',
            'starting_no' => 0,
            'ending_no' => null,
            'increment_by' => 1,
            'last_no_used' => 0,
            'no_of_digits' => 6,
            'blocked' => false,
        ]
    );
}

function createPayrollEmployee(PayrollPostingGroup $postingGroup, bool $active, ?float $baseSalary): Employee
{
    $employee = Employee::factory()->create([
        'is_active' => $active,
        'payroll_posting_group_id' => $postingGroup->id,
    ]);

    if ($baseSalary !== null) {
        $employee->compensations()->create([
            'base_salary' => $baseSalary,
            'effective_date' => '2026-06-01',
        ]);
    }

    return $employee;
}

function payrollDocument(PayrollStatus $status): PayrollDocument
{
    $period = PayrollPeriod::query()->firstOrCreate([
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
    ], [
        'payment_date' => '2026-06-30',
        'status' => PayrollPeriodStatus::OPEN,
    ]);

    return PayrollDocument::query()->create([
        'document_number' => 'PAY-'.fake()->unique()->numerify('####'),
        'payroll_period_id' => $period->id,
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'status' => $status,
    ]);
}

function payrollLines(PayrollDocument $document, Employee $employee, PayCode $salaryCode, PayCode $taxCode): void
{
    $document->lines()->create([
        'employee_id' => $employee->id,
        'pay_code_id' => $salaryCode->id,
        'line_type' => PayCodeType::EARNING->getLabel(),
        'amount' => 5000,
        'description' => 'Base Salary',
    ]);

    $document->lines()->create([
        'employee_id' => $employee->id,
        'pay_code_id' => $taxCode->id,
        'line_type' => PayCodeType::DEDUCTION->getLabel(),
        'amount' => 1000,
        'description' => 'PAYE',
    ]);
}

function grantPayrollPermission(User $user, string $permission): void
{
    Permission::query()->firstOrCreate([
        'name' => $permission,
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
}

function ensureOpenPostingPeriod(): void
{
    GeneralLedgerSetup::query()->firstOrCreate(['company_name' => 'Default Company'], [
        'allow_posting_from' => '2026-01-01',
        'allow_posting_to' => '2026-12-31',
    ]);

    AccountingPeriod::query()->firstOrCreate([
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ], [
        'name' => 'FY2026',
        'is_closed' => false,
    ]);
}
