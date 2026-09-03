<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use App\Enums\IncomeBalanceType;
use App\Enums\SourceType;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\PostingTransaction;
use App\Models\User;
use App\Services\Finance\BankOpeningBalanceRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('reports the exact append-only correction without changing historical rows in dry-run mode', function (): void {
    [$bank, $equity, $transaction, $user] = bankRepairFixture();

    $result = app(BankOpeningBalanceRepairService::class)->analyze($bank);

    expect($result['amount'])->toBe('5000.00')
        ->and($result['bank_gl_account_id'])->toBe($bank->gl_account_id)
        ->and($result['equity_account_id'])->toBe($equity->id)
        ->and($result['original_transaction_id'])->toBe($transaction->id)
        ->and($result['correction_exists'])->toBeFalse()
        ->and(BankAccountLedgerEntry::query()->count())->toBe(1)
        ->and(GlEntry::query()->count())->toBe(2)
        ->and(PostingTransaction::query()->count())->toBe(1);
});

it('applies one correction without creating a bank ledger entry or changing cached balances', function (): void {
    [$bank, $equity, , $user] = bankRepairFixture();
    $beforeBalance = $bank->current_balance;

    expect(ChartOfAccount::query()->find($bank->gl_account_id))->not->toBeNull()
        ->and(ChartOfAccount::query()->find($equity->id))->not->toBeNull();
    $result = app(BankOpeningBalanceRepairService::class)->repair($bank, $user->id);

    expect($result['repaired'])->toBeTrue()
        ->and(BankAccountLedgerEntry::query()->count())->toBe(1)
        ->and((float) $bank->fresh()->current_balance)->toBe((float) $beforeBalance)
        ->and((float) GlEntry::query()->where('chart_of_account_id', $bank->gl_account_id)->sum('debit_amount'))
        ->toBe(10000.0)
        ->and((float) GlEntry::query()->where('chart_of_account_id', $equity->id)->sum('credit_amount'))
        ->toBe(5000.0);

    $second = app(BankOpeningBalanceRepairService::class)->repair($bank->fresh(), $user->id);
    expect($second['idempotent'])->toBeTrue()
        ->and(PostingTransaction::query()->where('document_type', 'BANK_OPENING_CORRECTION')->count())->toBe(1);
});

it('reports a valid correction as informational without requiring a second bank ledger entry', function (): void {
    [$bank, , , $user] = bankRepairFixture();

    app(BankOpeningBalanceRepairService::class)->repair($bank, $user->id);

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['valid_bank_opening_balance_corrections'])->toHaveCount(1)
        ->and($report['valid_bank_opening_balance_corrections'][0]['severity'])->toBe('info')
        ->and(collect($report['missing_control_account_entries'])
            ->where('document_type', 'BANK_OPENING_CORRECTION')
            ->isEmpty())->toBeTrue()
        ->and($report['invalid_bank_opening_balance_corrections'])->toBeEmpty()
        ->and(BankAccountLedgerEntry::query()->where('document_type', 'BANK_OPENING_CORRECTION')->count())->toBe(0);
});

it('reports a malformed bank opening correction instead of suppressing it', function (): void {
    [$bank, , , $user] = bankRepairFixture();

    $result = app(BankOpeningBalanceRepairService::class)->repair($bank, $user->id);
    $correction = PostingTransaction::query()->findOrFail($result['correction_transaction_id']);
    $wrongAccount = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
    $correction->glEntries()->where('debit_amount', '>', 0)->update(['chart_of_account_id' => $wrongAccount->id]);

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);
    expect($report['valid_bank_opening_balance_corrections'])->toBeEmpty()
        ->and($report['invalid_bank_opening_balance_corrections'])->not->toBeEmpty();
});

function bankRepairFixture(): array
{
    $user = User::factory()->create();
    $bankAccount = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
    $equity = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::EQUITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
    $bank = BankAccount::factory()->create([
        'gl_account_id' => $bankAccount->id,
        'current_balance' => 5000,
        'available_balance' => 5000,
    ]);
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);
    AccountingPeriod::query()->create([
        'name' => 'FY2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $transaction = PostingTransaction::query()->create([
        'source_module' => 'finance',
        'source_type' => SourceType::BANK->value,
        'source_id' => $bank->id,
        'source_number' => 'OB-BANK-'.$bank->id,
        'document_type' => 'OPENING_BALANCE',
        'document_number' => 'OB-BANK-'.$bank->id,
        'transaction_key' => 'OPENING_BALANCE:BANK:'.$bank->id,
        'idempotency_key' => 'OPENING_BALANCE:BANK:'.$bank->id,
        'posting_date' => '2026-08-29',
        'document_date' => '2026-08-29',
        'currency_code' => 'NGN',
        'status' => 'completed',
    ]);

    $debit = GlEntry::query()->create(bankRepairGlAttributes($transaction, $bank, $bankAccount, 5000, 0));
    GlEntry::query()->create(bankRepairGlAttributes($transaction, $bank, $bankAccount, 0, 5000));

    BankAccountLedgerEntry::query()->create([
        'entry_number' => 1,
        'bank_account_id' => $bank->id,
        'bank_account_no' => $bank->account_number,
        'posting_date' => '2026-08-29',
        'document_date' => '2026-08-29',
        'document_type' => 'OPENING_BALANCE',
        'document_no' => 'OB-BANK-'.$bank->id,
        'description' => 'Historical bank opening balance',
        'entry_type' => BankAccountLedgerEntryType::DEPOSIT,
        'amount' => 5000,
        'amount_lcy' => 5000,
        'debit_amount' => 5000,
        'credit_amount' => 0,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'balance' => 5000,
        'balance_lcy' => 5000,
        'user_id' => $user->id,
        'status' => BankAccountLedgerEntryStatus::OPEN,
        'open' => true,
        'gl_entry_id' => $debit->id,
        'source_type' => SourceType::BANK->value,
        'source_id' => $bank->id,
        'source_no' => 'OB-BANK-'.$bank->id,
    ]);

    return [$bank, $equity, $transaction, $user];
}

function bankRepairGlAttributes(PostingTransaction $transaction, BankAccount $bank, ChartOfAccount $account, int $debit, int $credit): array
{
    return [
        'entry_number' => $transaction->id * 10 + ($debit > 0 ? 1 : 2),
        'transaction_number' => $transaction->id,
        'posting_transaction_id' => $transaction->id,
        'chart_of_account_id' => $account->id,
        'debit_amount' => $debit,
        'debit_amount_lcy' => $debit,
        'credit_amount' => $credit,
        'credit_amount_lcy' => $credit,
        'amount' => $debit - $credit,
        'amount_lcy' => $debit - $credit,
        'source_type' => SourceType::BANK->value,
        'source_id' => $bank->id,
        'source_number' => 'OB-BANK-'.$bank->id,
        'document_type' => 'OPENING_BALANCE',
        'document_number' => 'OB-BANK-'.$bank->id,
        'document_date' => '2026-08-29',
        'posting_date' => '2026-08-29',
        'description' => 'Historical bank opening balance',
    ];
}
