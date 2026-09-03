<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('renders vendor and customer ledger indexes with decimal monetary values', function (): void {
    $user = ledgerIndexSuperAdmin();
    $vendor = Vendor::factory()->create();
    $customer = Customer::factory()->create();

    $vendorEntry = VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendor->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-FCY-001',
        'description' => 'Foreign currency purchase',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
        'debit_amount' => '101500.0000',
        'credit_amount' => '0.0000',
        'amount' => '101500.0000',
        'running_balance' => '101500.0000',
        'remaining_amount' => '101500.0000',
        'open' => true,
        'currency_code' => 'USD',
        'original_debit_amount' => '70.0000',
        'original_credit_amount' => '0.0000',
        'currency_factor' => '1450.000000',
        'created_by' => $user->id,
    ]);

    $customerEntry = CustomerLedgerEntry::query()->create([
        'entry_number' => 1,
        'customer_id' => $customer->id,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-FCY-001',
        'description' => 'Foreign currency sale',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
        'debit_amount' => '101500.0000',
        'credit_amount' => '0.0000',
        'amount' => '101500.0000',
        'running_balance' => '101500.0000',
        'remaining_amount' => '101500.0000',
        'open' => true,
        'currency_code' => 'USD',
        'original_debit_amount' => '70.0000',
        'original_credit_amount' => '0.0000',
        'currency_factor' => '1450.000000',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withSession(ledgerIndexPassedTwoFactorSession())
        ->get('/admin/vendor-ledger-entries')
        ->assertSuccessful()
        ->assertSee('PI-FCY-001')
        ->assertSee('Debit (LCY)')
        ->assertSee('NGN');

    $this->actingAs($user)
        ->withSession(ledgerIndexPassedTwoFactorSession())
        ->get('/admin/customer-ledger-entries')
        ->assertSuccessful()
        ->assertSee('SI-FCY-001')
        ->assertSee('Debit (LCY)')
        ->assertSee('NGN');

    $this->actingAs($user)
        ->withSession(ledgerIndexPassedTwoFactorSession())
        ->get('/admin/vendor-ledger-entries/'.$vendorEntry->id)
        ->assertSuccessful()
        ->assertSee('Foreign currency purchase');

    $this->actingAs($user)
        ->withSession(ledgerIndexPassedTwoFactorSession())
        ->get('/admin/customer-ledger-entries/'.$customerEntry->id)
        ->assertSuccessful()
        ->assertSee('Foreign currency sale');
});

function ledgerIndexSuperAdmin(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

function ledgerIndexPassedTwoFactorSession(): array
{
    return [
        'two_factor_passed_at' => now()->timestamp,
        'super_admin_2fa_passed_at' => now()->timestamp,
    ];
}
