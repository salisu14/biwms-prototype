<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use App\Exceptions\BusinessException;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\User;
use App\Services\BankAccountLedgerService;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        [
            'allow_posting_from' => '2026-01-01',
            'allow_posting_to' => '2026-12-31',
        ],
    );
    AccountingPeriod::query()->create([
        'name' => 'FY2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    bankOpeningNumberSeries();
});

it('posts an opening bank balance once through the bank ledger and G/L', function (): void {
    $user = bankOpeningUser();
    [$bank, $offset] = bankOpeningAccounts();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $offset->id]);

    $entry = app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank,
        '5000000.0000',
        $offset->id,
        '2026-08-29',
        'Opening bank funding',
        'CUTOVER-001',
        $user->id,
    );

    expect(BankAccountLedgerEntry::query()->where('bank_account_id', $bank->id)->count())->toBe(1)
        ->and((float) $entry->amount)->toBe(5000000.0)
        ->and((float) $bank->fresh()->current_balance)->toBe(5000000.0)
        ->and((float) $bank->fresh()->available_balance)->toBe(5000000.0)
        ->and($entry->gl_entry_id)->not->toBeNull()
        ->and((float) GlEntry::query()->where('document_number', 'OB-BANK-'.$bank->id)->where('chart_of_account_id', $bank->gl_account_id)->sum('debit_amount'))->toBe(5000000.0)
        ->and((float) GlEntry::query()->where('document_number', 'OB-BANK-'.$bank->id)->where('chart_of_account_id', $offset->id)->sum('credit_amount'))->toBe(5000000.0);

    expect(fn () => app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank->fresh(), '5000000', $offset->id, '2026-08-29', 'Retry', null, $user->id,
    ))->toThrow(BusinessException::class, 'An opening balance has already been posted');

    expect(BankAccountLedgerEntry::query()->where('bank_account_id', $bank->id)->count())->toBe(1);
});

it('rolls back when the opening offset account is invalid', function (): void {
    $user = bankOpeningUser();
    [$bank] = bankOpeningAccounts();

    expect(fn () => app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank, '5000', 999999, '2026-08-29', 'Invalid opening', null, $user->id,
    ))->toThrow(BusinessException::class);

    expect(BankAccountLedgerEntry::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0)
        ->and((float) $bank->fresh()->current_balance)->toBe(0.0);
});

it('rejects opening balances in a closed accounting period', function (): void {
    $user = bankOpeningUser();
    [$bank, $offset] = bankOpeningAccounts();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $offset->id]);
    AccountingPeriod::query()->update(['is_closed' => true]);

    expect(fn () => app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank, '5000', $offset->id, '2026-08-29', 'Closed period', null, $user->id,
    ))->toThrow(ValidationException::class, 'closed');
});

it('enforces the opening balance permission at the service boundary', function (): void {
    $user = User::factory()->create();
    [$bank, $offset] = bankOpeningAccounts();

    expect(fn () => app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank, '5000', $offset->id, '2026-08-29', 'Unauthorized', null, $user->id,
    ))->toThrow(AuthorizationException::class);
});

it('uses the configured opening equity account instead of the submitted offset account', function (): void {
    $user = bankOpeningUser();
    [$bank, $offset] = bankOpeningAccounts();
    $otherAccount = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $offset->id]);

    app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank, '5000', $otherAccount->id, '2026-08-29', 'Configured equity', null, $user->id,
    );

    expect((float) GlEntry::query()->where('document_number', 'OB-BANK-'.$bank->id)
        ->where('chart_of_account_id', $bank->gl_account_id)->sum('debit_amount'))->toBe(5000.0)
        ->and((float) GlEntry::query()->where('document_number', 'OB-BANK-'.$bank->id)
            ->where('chart_of_account_id', $offset->id)->sum('credit_amount'))->toBe(5000.0)
        ->and(GlEntry::query()->where('document_number', 'OB-BANK-'.$bank->id)
            ->where('chart_of_account_id', $otherAccount->id)->count())->toBe(0);
});

it('rejects missing opening equity setup without creating any posting rows', function (): void {
    $user = bankOpeningUser();
    [$bank, $offset] = bankOpeningAccounts();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => null]);

    expect(fn () => app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank, '5000', $offset->id, '2026-08-29', 'Missing setup', null, $user->id,
    ))->toThrow(BusinessException::class, 'Configure an Opening Balance Equity account');

    expect(BankAccountLedgerEntry::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0)
        ->and((float) $bank->fresh()->current_balance)->toBe(0.0);
});

it('rejects an opening equity account equal to the bank account and rolls back', function (): void {
    $user = bankOpeningUser();
    [$bank, $offset] = bankOpeningAccounts();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $bank->gl_account_id]);

    expect(fn () => app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank, '5000', $offset->id, '2026-08-29', 'Same account', null, $user->id,
    ))->toThrow(BusinessException::class, 'cannot be the same');

    expect(BankAccountLedgerEntry::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0)
        ->and((float) $bank->fresh()->current_balance)->toBe(0.0);
});

it('rejects non-equity, heading, blocked, and system-controlled opening accounts', function (): void {
    $user = bankOpeningUser();
    [$bank, $offset] = bankOpeningAccounts();
    $invalidAccounts = [
        ChartOfAccount::factory()->create(['account_category' => AccountCategory::ASSET]),
        ChartOfAccount::factory()->create(['structural_type' => AccountStructuralType::HEADING]),
        ChartOfAccount::factory()->create(['account_category' => AccountCategory::EQUITY, 'blocked' => true]),
    ];
    $controlled = ChartOfAccount::factory()->create(['account_category' => AccountCategory::EQUITY]);
    BankAccount::factory()->create(['gl_account_id' => $controlled->id]);
    $invalidAccounts[] = $controlled;

    foreach ($invalidAccounts as $invalidAccount) {
        GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $invalidAccount->id]);

        expect(fn () => app(BankAccountLedgerService::class)->postOpeningBalance(
            $bank->fresh(), '5000', $offset->id, '2026-08-29', 'Invalid setup', null, $user->id,
        ))->toThrow(BusinessException::class);
    }

    expect(BankAccountLedgerEntry::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0);
});

it('rolls back the bank opening balance when the G/L posting fails', function (): void {
    $user = bankOpeningUser();
    [$bank, $offset] = bankOpeningAccounts();
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $offset->id]);
    $mock = Mockery::mock(GeneralLedgerService::class);
    $mock->shouldReceive('postTransaction')->once()->andThrow(new RuntimeException('G/L unavailable'));
    app()->instance(GeneralLedgerService::class, $mock);

    expect(fn () => app(BankAccountLedgerService::class)->postOpeningBalance(
        $bank, '5000', $offset->id, '2026-08-29', 'G/L failure', null, $user->id,
    ))->toThrow(RuntimeException::class, 'G/L unavailable');

    expect(BankAccountLedgerEntry::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0)
        ->and((float) $bank->fresh()->current_balance)->toBe(0.0);
});

function bankOpeningUser(): User
{
    $user = User::factory()->create();
    Permission::query()->firstOrCreate(['name' => 'finance.bank_account.opening_balance', 'guard_name' => 'web']);
    $user->givePermissionTo('finance.bank_account.opening_balance');

    return $user;
}

/** @return array{0: BankAccount, 1: ChartOfAccount} */
function bankOpeningAccounts(): array
{
    $bankAccount = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
    $offset = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::EQUITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);

    return [BankAccount::factory()->create(['gl_account_id' => $bankAccount->id]), $offset];
}

function bankOpeningNumberSeries(): void
{
    $series = NumberSeries::query()->firstOrCreate(
        ['code' => 'BANK-LEDGER'],
        ['description' => 'Bank ledger', 'prefix' => '', 'starting_number' => 1, 'current_number' => 0, 'year' => 2026, 'is_active' => true, 'module' => 'finance'],
    );
    NumberSeriesLine::query()->firstOrCreate(
        ['number_series_id' => $series->id, 'starting_date' => '2026-01-01'],
        ['starting_no' => 0, 'increment_by' => 1, 'last_no_used' => 0, 'no_of_digits' => 6, 'blocked' => false],
    );
}
