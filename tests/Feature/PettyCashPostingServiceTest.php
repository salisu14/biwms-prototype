<?php

use App\Enums\PettyCashTransactionType;
use App\Enums\PettyCashVoucherStatus;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\PettyCashVoucher;
use App\Models\PettyCashVoucherLine;
use App\Models\User;
use App\Services\PettyCashPostingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->refreshPostgresTestingDatabase();
    ensurePettyCashPostingNumberSeries();
    ensurePettyCashBankLedgerNumberSeries();
});

it('posts an approved petty cash voucher to petty cash ledger and general ledger', function () {
    $user = User::factory()->create();
    grantPettyCashPostingPermission($user);

    $pettyCashAccount = ChartOfAccount::factory()->create();
    $expenseAccount = ChartOfAccount::factory()->create();

    $fund = PettyCashFund::query()->create([
        'code' => 'PC-MAIN',
        'name' => 'Main Office Petty Cash',
        'current_balance' => 1000,
        'imprest_amount' => 1000,
        'currency' => 'NGN',
        'is_active' => true,
        'chart_of_account_id' => $pettyCashAccount->id,
    ]);

    $voucher = PettyCashVoucher::query()->create([
        'voucher_number' => 'PCV-001',
        'petty_cash_fund_id' => $fund->id,
        'date' => now(),
        'payee_name' => 'Office Supplies Vendor',
        'purpose' => 'Office supplies',
        'total_amount' => 250,
        'status' => PettyCashVoucherStatus::APPROVED,
        'requested_by_id' => $user->id,
        'approved_by_id' => $user->id,
    ]);

    PettyCashVoucherLine::query()->create([
        'petty_cash_voucher_id' => $voucher->id,
        'line_number' => 10000,
        'expense_account_id' => $expenseAccount->id,
        'description' => 'Paper and toner',
        'amount' => 250,
    ]);

    app(PettyCashPostingService::class)->postVoucher($voucher, $user->id);

    $transaction = PettyCashTransaction::query()
        ->where('petty_cash_voucher_id', $voucher->id)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe(PettyCashTransactionType::PAYMENT)
        ->and((float) $transaction->amount)->toBe(-250.0)
        ->and((float) $transaction->running_balance)->toBe(750.0)
        ->and((float) $fund->fresh()->current_balance)->toBe(750.0)
        ->and($voucher->fresh()->status)->toBe(PettyCashVoucherStatus::POSTED);

    $cashEntry = GlEntry::query()
        ->where('document_number', $voucher->voucher_number)
        ->where('chart_of_account_id', $pettyCashAccount->id)
        ->first();

    $expenseEntry = GlEntry::query()
        ->where('document_number', $voucher->voucher_number)
        ->where('chart_of_account_id', $expenseAccount->id)
        ->first();

    expect($cashEntry)->not->toBeNull()
        ->and((float) $cashEntry->debit_amount)->toBe(0.0)
        ->and((float) $cashEntry->credit_amount)->toBe(250.0)
        ->and($expenseEntry)->not->toBeNull()
        ->and((float) $expenseEntry->debit_amount)->toBe(250.0)
        ->and((float) $expenseEntry->credit_amount)->toBe(0.0);
});

it('blocks double posting for petty cash vouchers', function () {
    $user = User::factory()->create();
    grantPettyCashPostingPermission($user);

    $pettyCashAccount = ChartOfAccount::factory()->create();
    $expenseAccount = ChartOfAccount::factory()->create();

    $fund = PettyCashFund::query()->create([
        'code' => 'PC-SALES',
        'name' => 'Sales Office Petty Cash',
        'current_balance' => 500,
        'imprest_amount' => 500,
        'currency' => 'NGN',
        'is_active' => true,
        'chart_of_account_id' => $pettyCashAccount->id,
    ]);

    $voucher = PettyCashVoucher::query()->create([
        'voucher_number' => 'PCV-002',
        'petty_cash_fund_id' => $fund->id,
        'date' => now(),
        'payee_name' => 'Courier',
        'purpose' => 'Courier fee',
        'total_amount' => 100,
        'status' => PettyCashVoucherStatus::APPROVED,
        'requested_by_id' => $user->id,
        'approved_by_id' => $user->id,
    ]);

    PettyCashVoucherLine::query()->create([
        'petty_cash_voucher_id' => $voucher->id,
        'line_number' => 10000,
        'expense_account_id' => $expenseAccount->id,
        'description' => 'Courier fee',
        'amount' => 100,
    ]);

    $service = app(PettyCashPostingService::class);
    $service->postVoucher($voucher, $user->id);

    expect(fn () => $service->postVoucher($voucher->fresh(), $user->id))
        ->toThrow(RuntimeException::class, 'Only approved petty cash vouchers can be posted.');

    expect(PettyCashTransaction::query()
        ->where('petty_cash_voucher_id', $voucher->id)
        ->count())->toBe(1);
});

it('posts a matching bank ledger entry when petty cash uses a bank backed cash account', function () {
    $user = User::factory()->create();
    grantPettyCashPostingPermission($user);

    $pettyCashAccount = ChartOfAccount::factory()->create();
    $expenseAccount = ChartOfAccount::factory()->create();

    $bankAccount = BankAccount::query()->create([
        'account_code' => 'PC-BANK',
        'account_name' => 'Petty Cash Bank Account',
        'bank_name' => 'Main Bank',
        'account_number' => 'PC-BANK-001',
        'gl_account_id' => $pettyCashAccount->id,
        'current_balance' => 1000,
        'available_balance' => 1000,
        'active' => true,
        'allow_payments' => true,
        'allow_receipts' => true,
    ]);

    $fund = PettyCashFund::query()->create([
        'code' => 'PC-BANK',
        'name' => 'Bank Backed Petty Cash',
        'current_balance' => 1000,
        'imprest_amount' => 1000,
        'currency' => 'NGN',
        'is_active' => true,
        'chart_of_account_id' => $pettyCashAccount->id,
    ]);

    $voucher = PettyCashVoucher::query()->create([
        'voucher_number' => 'PCV-BANK-001',
        'petty_cash_fund_id' => $fund->id,
        'date' => now(),
        'payee_name' => 'Stationery Vendor',
        'purpose' => 'Stationery',
        'total_amount' => 125,
        'status' => PettyCashVoucherStatus::APPROVED,
        'requested_by_id' => $user->id,
        'approved_by_id' => $user->id,
    ]);

    PettyCashVoucherLine::query()->create([
        'petty_cash_voucher_id' => $voucher->id,
        'line_number' => 10000,
        'expense_account_id' => $expenseAccount->id,
        'description' => 'Stationery',
        'amount' => 125,
    ]);

    app(PettyCashPostingService::class)->postVoucher($voucher, $user->id);

    $bankLedgerEntry = BankAccountLedgerEntry::query()
        ->where('bank_account_id', $bankAccount->id)
        ->where('document_type', 'PETTY_CASH_VOUCHER')
        ->where('document_no', 'PCV-BANK-001')
        ->where('source_type', PettyCashVoucher::class)
        ->where('source_id', $voucher->id)
        ->first();

    expect($bankLedgerEntry)->not->toBeNull()
        ->and((float) $bankLedgerEntry->amount)->toBe(-125.0)
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(875.0);

    expect(GlEntry::query()
        ->where('document_type', 'PETTY_CASH_VOUCHER')
        ->where('document_number', 'PCV-BANK-001')
        ->where('chart_of_account_id', $pettyCashAccount->id)
        ->sum('amount'))->toBe('-125.00');

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);
    $report = json_decode(trim(Artisan::output()), true);

    expect($report['bank_ledger_gl_mismatches'])->toBeEmpty()
        ->and($report['missing_control_account_entries'])->toBeEmpty();
});

it('blocks petty cash posting without permission', function () {
    $user = User::factory()->create();
    $pettyCashAccount = ChartOfAccount::factory()->create();
    $expenseAccount = ChartOfAccount::factory()->create();

    $fund = PettyCashFund::query()->create([
        'code' => 'PC-HR',
        'name' => 'HR Petty Cash',
        'current_balance' => 500,
        'imprest_amount' => 500,
        'currency' => 'NGN',
        'is_active' => true,
        'chart_of_account_id' => $pettyCashAccount->id,
    ]);

    $voucher = PettyCashVoucher::query()->create([
        'voucher_number' => 'PCV-003',
        'petty_cash_fund_id' => $fund->id,
        'date' => now(),
        'payee_name' => 'Taxi',
        'purpose' => 'Local transport',
        'total_amount' => 80,
        'status' => PettyCashVoucherStatus::APPROVED,
        'requested_by_id' => $user->id,
        'approved_by_id' => $user->id,
    ]);

    PettyCashVoucherLine::query()->create([
        'petty_cash_voucher_id' => $voucher->id,
        'line_number' => 10000,
        'expense_account_id' => $expenseAccount->id,
        'description' => 'Taxi',
        'amount' => 80,
    ]);

    expect(fn () => app(PettyCashPostingService::class)->postVoucher($voucher, $user->id))
        ->toThrow(AuthorizationException::class);

    expect($voucher->fresh()->status)->toBe(PettyCashVoucherStatus::APPROVED)
        ->and(PettyCashTransaction::query()->where('petty_cash_voucher_id', $voucher->id)->exists())->toBeFalse();
});

function grantPettyCashPostingPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'finance.petty_cash_voucher.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('finance.petty_cash_voucher.post');
}

function ensurePettyCashPostingNumberSeries(): void
{
    $series = NumberSeries::query()->updateOrCreate(
        ['code' => 'PC-TRANS'],
        [
            'description' => 'Petty Cash Transactions',
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

    $series->lines()->delete();

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => '',
        'suffix' => '',
        'blocked' => false,
    ]);
}

function ensurePettyCashBankLedgerNumberSeries(): void
{
    $series = NumberSeries::query()->updateOrCreate(
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

    $series->lines()->delete();

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => '',
        'suffix' => '',
        'blocked' => false,
    ]);
}
