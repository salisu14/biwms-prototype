<?php

declare(strict_types=1);

use App\Enums\CustomerReferralStatus;
use App\Models\Customer;
use App\Models\CustomerReferral;
use App\Models\Permission;
use App\Models\Referrer;
use App\Models\Role;
use App\Models\User;
use App\Policies\CustomerReferralPolicy;
use App\Services\Sales\CustomerReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    referralFilamentRole('sales-manager', [
        'sales.customer.view_any',
        'sales.customer.view',
        'sales.customer.create',
        'sales.customer.update',
        'sales.referrer.view_any',
        'sales.referrer.view',
        'sales.referrer.create',
        'sales.customer_referral.view_any',
        'sales.customer_referral.view',
        'sales.customer_referral.create',
        'sales.customer_referral.update',
        'sales.customer_referral.assign',
        'sales.customer_referral.change',
        'sales.customer_referral.suspend',
        'sales.customer_referral.reactivate',
        'sales.customer_referral.end',
        'sales.customer_referral.cancel',
    ]);

    referralFilamentRole('sales-representative', [
        'sales.customer_referral.view_any',
        'sales.customer_referral.view',
    ]);

    referralFilamentRole('finance-manager', [
        'sales.customer_referral.view_any',
        'sales.customer_referral.view',
    ]);
});

function referralFilamentRole(string $roleName, array $permissions): Role
{
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $role = Role::query()->firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);
    $role->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $role;
}

function referralFilamentUser(string $roleName): User
{
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole(Role::query()->where('name', $roleName)->firstOrFail());

    return $user;
}

it('renders customer referral pages and relation managers', function (): void {
    $user = referralFilamentUser('sales-manager');
    $customer = Customer::factory()->create();
    $referrer = Referrer::factory()->create();
    $referral = app(CustomerReferralService::class)->assign($customer, $referrer, ['effective_from' => today()], $user->id);

    foreach ([
        '/admin/customer-referrals',
        '/admin/customer-referrals/create',
        "/admin/customer-referrals/{$referral->id}",
        "/admin/customer-referrals/{$referral->id}/edit",
        "/admin/customers/{$customer->id}",
        "/admin/referrers/{$referrer->id}",
    ] as $url) {
        $this->actingAs($user)
            ->withSession(['two_factor_passed_at' => now()->timestamp])
            ->get($url)
            ->assertSuccessful();
    }
});

it('allows customer creation without referrer and with referrer through the form data path', function (): void {
    $customer = Customer::factory()->create();
    $referrer = Referrer::factory()->create();

    expect($customer->primaryReferral)->toBeNull();

    app(CustomerReferralService::class)->assign($customer, $referrer, [
        'effective_from' => today(),
        'referral_source' => 'Website',
    ]);

    expect($customer->refresh()->primaryReferral?->referrer_id)->toBe($referrer->id);
});

it('resolves policy and enforces workflow permissions', function (): void {
    $manager = referralFilamentUser('sales-manager');
    $salesperson = referralFilamentUser('sales-representative');
    $finance = referralFilamentUser('finance-manager');
    $unauthorized = User::factory()->create();
    $referral = CustomerReferral::factory()->create(['status' => CustomerReferralStatus::ACTIVE]);

    expect(Gate::getPolicyFor(CustomerReferral::class))->toBeInstanceOf(CustomerReferralPolicy::class)
        ->and($manager->can('assign', CustomerReferral::class))->toBeTrue()
        ->and($manager->can('change', $referral))->toBeTrue()
        ->and($manager->can('cancel', $referral))->toBeTrue()
        ->and($salesperson->can('cancel', $referral))->toBeFalse()
        ->and($finance->can('view', $referral))->toBeTrue()
        ->and($finance->can('change', $referral))->toBeFalse()
        ->and($unauthorized->can('view', $referral))->toBeFalse();
});
