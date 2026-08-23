<?php

declare(strict_types=1);

use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('renders the bank account ledger entry view with decimal string monetary states', function (string $amount): void {
    $user = createBankLedgerSuperAdmin();
    $entry = createBankAccountLedgerEntry($user, $amount);

    expect($entry->amount)->toBeString();

    $this->actingAs($user)
        ->withSession(bankLedgerPassedTwoFactorSession())
        ->get("/admin/bank-account-ledger-entries/{$entry->getKey()}")
        ->assertSuccessful()
        ->assertSee($entry->document_no)
        ->assertSee('PAY-2026-00002')
        ->assertSee('G/L Entries')
        ->assertSee('Customer Entries')
        ->assertSee('Vendor Entries');
})->with([
    'positive deposit amount' => '50000.0000',
    'negative withdrawal amount' => '-1500.0000',
    'zero amount' => '0.0000',
]);

it('renders the bank account ledger entry list with decimal string monetary states', function (): void {
    $user = createBankLedgerSuperAdmin();

    createBankAccountLedgerEntry($user, '50000.0000', ['document_no' => 'PAY-2026-00002']);
    createBankAccountLedgerEntry($user, '-1500.0000', ['entry_number' => 3, 'document_no' => 'PAY-2026-00003']);

    $this->actingAs($user)
        ->withSession(bankLedgerPassedTwoFactorSession())
        ->get('/admin/bank-account-ledger-entries')
        ->assertSuccessful()
        ->assertSee('PAY-2026-00002')
        ->assertSee('PAY-2026-00003');
});

it('renders null optional ledger links with placeholders instead of throwing', function (): void {
    $user = createBankLedgerSuperAdmin();
    $entry = createBankAccountLedgerEntry($user, '0.0000', [
        'gl_entry_id' => null,
        'customer_ledger_entry_id' => null,
        'vendor_ledger_entry_id' => null,
        'transfer_entry_id' => null,
    ]);

    expect($entry->gl_entry_id)->toBeNull()
        ->and($entry->customer_ledger_entry_id)->toBeNull()
        ->and($entry->vendor_ledger_entry_id)->toBeNull();

    $this->actingAs($user)
        ->withSession(bankLedgerPassedTwoFactorSession())
        ->get("/admin/bank-account-ledger-entries/{$entry->getKey()}")
        ->assertSuccessful()
        ->assertSee('Source &amp; Ledger Links', false)
        ->assertSee('-', false);
});

function createBankLedgerSuperAdmin(): User
{
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole('super_admin');

    return $user;
}

/**
 * @return array<string, int>
 */
function bankLedgerPassedTwoFactorSession(): array
{
    return [
        'two_factor_passed_at' => now()->timestamp,
        'super_admin_2fa_passed_at' => now()->timestamp,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createBankAccountLedgerEntry(User $user, string $amount, array $overrides = []): BankAccountLedgerEntry
{
    $bankAccount = BankAccount::factory()->create([
        'account_number' => '10100',
        'current_balance' => '51800.0000',
        'available_balance' => '51800.0000',
    ]);

    return BankAccountLedgerEntry::query()->create([
        'entry_number' => 2,
        'bank_account_id' => $bankAccount->id,
        'bank_account_no' => $bankAccount->account_number,
        'posting_date' => '2026-08-23',
        'document_date' => '2026-08-23',
        'due_date' => null,
        'document_type' => 'PAYMENT',
        'document_no' => 'PAY-2026-00002',
        'external_document_no' => null,
        'description' => 'Customer payment receipt',
        'description_2' => null,
        'entry_type' => ((float) $amount) < 0 ? BankAccountLedgerEntryType::WITHDRAWAL : BankAccountLedgerEntryType::DEPOSIT,
        'amount' => $amount,
        'amount_lcy' => $amount,
        'debit_amount' => ((float) $amount) > 0 ? $amount : '0.0000',
        'credit_amount' => ((float) $amount) < 0 ? (string) abs((float) $amount) : '0.0000',
        'currency_code' => 'NGN',
        'currency_factor' => '1.000000',
        'balance' => '51800.0000',
        'balance_lcy' => '51800.0000',
        'status' => BankAccountLedgerEntryStatus::OPEN,
        'open' => true,
        'gl_entry_id' => null,
        'customer_ledger_entry_id' => null,
        'vendor_ledger_entry_id' => null,
        'transfer_entry_id' => null,
        'source_type' => Payment::class,
        'source_id' => 2,
        'source_no' => 'PAY-2026-00002',
        'user_id' => $user->id,
        ...$overrides,
    ]);
}
