<?php

use App\Enums\AccountCategory;
use App\Enums\IncomeBalanceType;
use App\Models\ChartOfAccount;
use App\Models\CustomerPostingGroup;
use App\Models\VendorPostingGroup;
use Illuminate\Validation\ValidationException;

function controlAccount(string $number, AccountCategory $category): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'account_number' => $number,
        'account_category' => $category,
        'income_balance' => $category->isIncomeStatement()
            ? IncomeBalanceType::INCOME_STATEMENT
            : IncomeBalanceType::BALANCE_SHEET,
    ]);
}

function unusedAccountNumber(): string
{
    do {
        $number = (string) random_int(100000, 999999);
    } while (ChartOfAccount::query()->where('account_number', $number)->exists());

    return $number;
}

it('accepts a balance sheet receivables account for a customer posting group', function (): void {
    $account = controlAccount(unusedAccountNumber(), AccountCategory::RECEIVABLE);

    $group = CustomerPostingGroup::factory()->create([
        'receivables_account_id' => $account->id,
    ]);

    expect($group->fresh()->receivables_account_id)->toBe($account->id);
});

it('rejects income statement revenue and expense accounts for customer receivables', function (AccountCategory $category): void {
    $account = controlAccount(unusedAccountNumber(), $category);

    expect(fn (): CustomerPostingGroup => CustomerPostingGroup::factory()->create([
        'receivables_account_id' => $account->id,
    ]))->toThrow(ValidationException::class, 'Receivables Account');
})->with([
    AccountCategory::REVENUE,
    AccountCategory::OPERATING_EXPENSE,
]);

it('accepts only balance sheet liability or payable accounts for vendor payables', function (): void {
    $account = controlAccount(unusedAccountNumber(), AccountCategory::PAYABLE);

    $group = VendorPostingGroup::factory()->create([
        'payables_account_id' => $account->id,
    ]);

    expect($group->fresh()->payables_account_id)->toBe($account->id);
});

it('rejects asset and revenue accounts for vendor payables', function (AccountCategory $category): void {
    $account = controlAccount(unusedAccountNumber(), $category);

    expect(fn (): VendorPostingGroup => VendorPostingGroup::factory()->create([
        'payables_account_id' => $account->id,
    ]))->toThrow(ValidationException::class, 'Payables Account');
})->with([
    AccountCategory::ASSET,
    AccountCategory::REVENUE,
]);

it('cannot bypass control-account validation through direct model updates', function (): void {
    $customer = CustomerPostingGroup::factory()->create();
    $vendor = VendorPostingGroup::factory()->create();
    $revenue = controlAccount(unusedAccountNumber(), AccountCategory::REVENUE);

    expect(fn (): bool => $customer->update(['receivables_account_id' => $revenue->id]))
        ->toThrow(ValidationException::class);
    expect(fn (): bool => $vendor->update(['payables_account_id' => $revenue->id]))
        ->toThrow(ValidationException::class);

    expect($customer->fresh()->receivables_account_id)->not->toBe($revenue->id);
    expect($vendor->fresh()->payables_account_id)->not->toBe($revenue->id);
});
