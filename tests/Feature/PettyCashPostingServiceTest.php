<?php

use App\Enums\PettyCashTransactionType;
use App\Enums\PettyCashVoucherStatus;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use App\Models\Permission;
use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\PettyCashVoucher;
use App\Models\PettyCashVoucherLine;
use App\Models\User;
use App\Services\PettyCashPostingService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->refreshPostgresTestingDatabase();
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
