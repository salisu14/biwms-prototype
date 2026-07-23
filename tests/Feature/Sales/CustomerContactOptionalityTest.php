<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    salesCustomerOptionalityRole();
});

function salesCustomerOptionalityRole(): Role
{
    $permissions = [
        'sales.customer.view_any',
        'sales.customer.view',
        'sales.customer.create',
        'sales.customer.update',
        'sales.customer.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $role = Role::query()->firstOrCreate([
        'name' => 'sales-manager',
        'guard_name' => 'web',
    ]);
    $role->syncPermissions($permissions);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $role;
}

function salesCustomerOptionalityUser(): User
{
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole(Role::query()->where('name', 'sales-manager')->firstOrFail());

    return $user;
}

function createCustomerWithoutContactForTest(): Customer
{
    return Customer::query()->create([
        'name' => 'Optional Contact Customer Ltd',
        'email' => 'optional-contact@example.test',
        'phone' => '+2348000000000',
        'general_business_posting_group_id' => GeneralBusinessPostingGroup::factory()->create()->getKey(),
        'customer_posting_group_id' => CustomerPostingGroup::factory()->create()->getKey(),
        'vat_bus_posting_group' => 'DOMESTIC',
        'blocked' => false,
        'credit_limit' => 0,
        'contact_id' => null,
    ]);
}

it('creates a customer without a contact and does not auto-create one', function (): void {
    $initialContactCount = Contact::query()->count();

    $customer = createCustomerWithoutContactForTest();

    expect($customer->refresh()->contact_id)->toBeNull()
        ->and(Contact::query()->count())->toBe($initialContactCount)
        ->and($customer->contact)->toBeNull()
        ->and($customer->name)->toBe('Optional Contact Customer Ltd');
});

it('keeps existing customer contact links working', function (): void {
    $contact = Contact::factory()->create(['name' => 'Primary Buyer']);

    $customer = Customer::factory()->create([
        'name' => 'Linked Contact Customer Ltd',
        'contact_id' => $contact->getKey(),
    ]);

    expect($customer->refresh()->contact_id)->toBe($contact->getKey())
        ->and($customer->contact?->name)->toBe('Primary Buyer');
});

it('renders customer pages when contact is null', function (): void {
    $user = salesCustomerOptionalityUser();
    $customer = createCustomerWithoutContactForTest();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/sales/customers')
        ->assertSuccessful();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/sales/customers/create')
        ->assertSuccessful();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get("/sales/customers/{$customer->getKey()}")
        ->assertSuccessful()
        ->assertSee('No contact linked');

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get("/sales/customers/{$customer->getKey()}/edit")
        ->assertSuccessful();
});
