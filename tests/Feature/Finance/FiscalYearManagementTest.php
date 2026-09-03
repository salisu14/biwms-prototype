<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Filament\Pages\FiscalYearManagement;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('offers only active direct-posting equity accounts for retained earnings', function (): void {
    $equity = ChartOfAccount::factory()->create([
        'account_number' => '32100',
        'name' => 'Retained Earnings',
        'account_category' => AccountCategory::EQUITY,
        'structural_type' => AccountStructuralType::POSTING,
        'direct_posting' => true,
        'blocked' => false,
    ]);
    $asset = ChartOfAccount::factory()->create(['account_number' => '11000', 'account_category' => AccountCategory::ASSET]);
    $expense = ChartOfAccount::factory()->create(['account_number' => '61000', 'account_category' => AccountCategory::OPERATING_EXPENSE]);
    $heading = ChartOfAccount::factory()->create([
        'account_number' => '30000',
        'account_category' => AccountCategory::EQUITY,
        'structural_type' => AccountStructuralType::HEADING,
        'direct_posting' => false,
    ]);
    $blocked = ChartOfAccount::factory()->create([
        'account_number' => '32200',
        'account_category' => AccountCategory::EQUITY,
        'blocked' => true,
    ]);

    $options = FiscalYearManagement::retainedEarningsAccountOptions();

    expect($options)->toHaveKey($equity->id)
        ->not->toHaveKey($asset->id)
        ->not->toHaveKey($expense->id)
        ->not->toHaveKey($heading->id)
        ->not->toHaveKey($blocked->id);
});

it('offers only direct-posting non-equity balance-sheet accounts as expense offsets', function (): void {
    $asset = ChartOfAccount::factory()->create(['account_number' => '11100', 'account_category' => AccountCategory::ASSET]);
    $liability = ChartOfAccount::factory()->create(['account_number' => '21100', 'account_category' => AccountCategory::LIABILITY]);
    $equity = ChartOfAccount::factory()->create(['account_number' => '32100', 'account_category' => AccountCategory::EQUITY]);
    $income = ChartOfAccount::factory()->create(['account_number' => '40100', 'account_category' => AccountCategory::REVENUE]);
    $heading = ChartOfAccount::factory()->create([
        'account_number' => '11000',
        'account_category' => AccountCategory::ASSET,
        'structural_type' => AccountStructuralType::HEADING,
        'direct_posting' => false,
    ]);
    $blocked = ChartOfAccount::factory()->create([
        'account_number' => '11200',
        'account_category' => AccountCategory::ASSET,
        'blocked' => true,
    ]);

    $options = FiscalYearManagement::expenseOffsetAccountOptions();

    expect($options)->toHaveKey($asset->id)
        ->toHaveKey($liability->id)
        ->not->toHaveKey($equity->id)
        ->not->toHaveKey($income->id)
        ->not->toHaveKey($heading->id)
        ->not->toHaveKey($blocked->id);
});

it('seeds retained earnings as an equity posting account', function (): void {
    $this->seed(ChartOfAccountSeeder::class);

    $account = ChartOfAccount::query()->where('account_number', '32100')->firstOrFail();

    expect($account->account_category)->toBe(AccountCategory::EQUITY)
        ->and($account->structural_type)->toBe(AccountStructuralType::POSTING)
        ->and($account->direct_posting)->toBeTrue()
        ->and($account->blocked)->toBeFalse()
        ->and(FiscalYearManagement::retainedEarningsAccountOptions())->toHaveKey($account->id);
});

it('persists selected fiscal setup accounts without changing account semantics', function (): void {
    $retained = ChartOfAccount::factory()->create(['account_category' => AccountCategory::EQUITY]);
    $offset = ChartOfAccount::factory()->create(['account_category' => AccountCategory::ASSET]);

    $setup = GeneralLedgerSetup::instance();
    $setup->update([
        'retained_earnings_account_id' => $retained->id,
        'default_expense_offset_account_id' => $offset->id,
    ]);

    expect($setup->fresh()->retained_earnings_account_id)->toBe($retained->id)
        ->and($setup->fresh()->default_expense_offset_account_id)->toBe($offset->id);
});
