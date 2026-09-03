<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\IncomeBalanceType;
use App\Models\AccountingPeriod;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\PostingTransaction;
use App\Models\SubledgerOpeningBalance;
use App\Models\User;
use App\Services\Finance\CustomerControlAccountReclassificationService;
use App\Services\Finance\SubledgerOpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('reclassifies a legacy customer opening balance through the posting kernel', function (): void {
    [$opening, $oldAccount, $targetAccount, $group] = customerReclassificationFixture();
    $originalLines = $opening->postingTransaction->glEntries->map(fn (GlEntry $line): array => [
        'account' => $line->chart_of_account_id,
        'debit' => $line->debit_amount,
        'credit' => $line->credit_amount,
    ])->all();

    $result = app(CustomerControlAccountReclassificationService::class)
        ->reclassifyCustomerReceivables($opening, $targetAccount, $opening->created_by);

    expect($result['reclassified'])->toBeTrue()
        ->and($group->fresh()->receivables_account_id)->toBe($targetAccount->id)
        ->and($opening->fresh()->control_account_id)->toBe($oldAccount->id)
        ->and($opening->fresh()->status)->toBe(SubledgerOpeningBalance::STATUS_POSTED)
        ->and($opening->fresh()->postingTransaction->glEntries->map(fn (GlEntry $line): array => [
            'account' => $line->chart_of_account_id,
            'debit' => $line->debit_amount,
            'credit' => $line->credit_amount,
        ])->all())->toBe($originalLines)
        ->and(PostingTransaction::query()->where('idempotency_key', $result['correction_key'])->count())->toBe(1)
        ->and(GlEntry::query()->where('posting_transaction_id', $result['correction_transaction_id'])->where('chart_of_account_id', $targetAccount->id)->value('debit_amount'))->toBe('350000.00')
        ->and(GlEntry::query()->where('posting_transaction_id', $result['correction_transaction_id'])->where('chart_of_account_id', $oldAccount->id)->value('credit_amount'))->toBe('350000.00');

    Artisan::call('biwms:finance-reconcile', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true);
    expect($report['customer_ledger_receivables_mismatches'])->toBeEmpty()
        ->and(collect($report['missing_control_account_entries'])
            ->where('document_number', $opening->document_number)
            ->isEmpty())->toBeTrue();
});

it('is idempotent and never creates a second correction transaction', function (): void {
    [$opening, , $targetAccount] = customerReclassificationFixture();
    $service = app(CustomerControlAccountReclassificationService::class);

    $first = $service->reclassifyCustomerReceivables($opening, $targetAccount, $opening->created_by);
    $second = $service->reclassifyCustomerReceivables($opening->fresh(), $targetAccount, $opening->created_by);

    expect($first['correction_transaction_id'])->toBe($second['correction_transaction_id'])
        ->and($second['idempotent'])->toBeTrue()
        ->and(PostingTransaction::query()->where('idempotency_key', $first['correction_key'])->count())->toBe(1)
        ->and(GlEntry::query()->where('posting_transaction_id', $first['correction_transaction_id'])->count())->toBe(2);
});

it('does not change configuration or post when the target is not a valid receivables account', function (): void {
    [$opening, , $targetAccount, $group] = customerReclassificationFixture();
    $invalid = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::REVENUE,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
    ]);

    expect(fn (): array => app(CustomerControlAccountReclassificationService::class)
        ->reclassifyCustomerReceivables($opening, $invalid, $opening->created_by))
        ->toThrow(ValidationException::class)
        ->and($group->fresh()->receivables_account_id)->toBe($opening->control_account_id)
        ->and(PostingTransaction::query()->where('document_type', 'CUSTOMER_CONTROL_RECLASSIFICATION')->exists())->toBeFalse();
});

/** @return array{0: SubledgerOpeningBalance, 1: ChartOfAccount, 2: ChartOfAccount, 3: CustomerPostingGroup} */
function customerReclassificationFixture(): array
{
    $user = User::factory()->create();
    $permissions = collect([
        'finance.subledger_opening_balance.view',
        'finance.subledger_opening_balance.create',
        'finance.subledger_opening_balance.post',
    ])->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->givePermissionTo($permissions->all());
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $business = Business::query()->create(['code' => 'RECLASS-001', 'name' => 'Reclassification Test Business']);
    session(['active_business_id' => $business->id]);
    $oldAccount = ChartOfAccount::factory()->create([
        'account_number' => '43488',
        'account_category' => AccountCategory::REVENUE,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
    ]);
    $targetAccount = ChartOfAccount::factory()->create([
        'account_number' => '11100',
        'account_category' => AccountCategory::RECEIVABLE,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $customer = Customer::factory()->create();
    $group = CustomerPostingGroup::query()->findOrFail($customer->customer_posting_group_id);
    DB::table('customer_posting_groups')->where('id', $group->id)->update(['receivables_account_id' => $oldAccount->id]);
    $customer->refresh();

    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => subledgerReclassificationEquity()->id]);
    AccountingPeriod::query()->create([
        'name' => 'FY2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $series = NumberSeries::query()->create([
        'code' => 'CUSTOMER-OPENING',
        'description' => 'Customer opening balances',
        'prefix' => 'COB',
        'starting_number' => 1,
        'ending_number' => 999999,
        'current_number' => 0,
        'year' => 2026,
        'is_active' => true,
        'module' => 'finance',
    ]);
    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 5,
        'blocked' => false,
    ]);

    $opening = app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $business->id,
        'party_type' => 'CUSTOMER',
        'party_id' => $customer->id,
        'original_amount' => '350000.00',
        'currency_code' => 'NGN',
        'currency_factor' => '1',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id);
    $opening = app(SubledgerOpeningBalanceService::class)->post($opening, $user->id);

    return [$opening->fresh(['postingTransaction.glEntries']), $oldAccount, $targetAccount, $group];
}

function subledgerReclassificationEquity(): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::EQUITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
}
