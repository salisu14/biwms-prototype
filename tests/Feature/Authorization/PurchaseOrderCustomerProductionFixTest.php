<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Policies\ContactPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    productionFixRoleWithPermissions('purchasing-agent', [
        'procurement.purchase_order.view_any',
        'procurement.purchase_order.view',
        'procurement.purchase_order.create',
        'procurement.purchase_order.update',
    ]);

    productionFixRoleWithPermissions('sales-manager', [
        'sales.customer.view_any',
        'sales.customer.view',
        'sales.customer.create',
        'sales.customer.update',
        'sales.customer.delete',
        'sales.customer_contact.view_any',
        'sales.customer_contact.view',
        'sales.customer_contact.create',
        'sales.customer_contact.update',
        'sales.customer_contact.delete',
    ]);
});

function productionFixRoleWithPermissions(string $roleName, array $permissionNames): Role
{
    foreach ($permissionNames as $permissionName) {
        Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }

    $role = Role::query()->firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $role->syncPermissions($permissionNames);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $role;
}

function productionFixUserWithRole(string $roleName): User
{
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);

    $user->assignRole(Role::query()->where('name', $roleName)->firstOrFail());

    return $user;
}

it('allows purchasing agents to open the purchase order create page', function (): void {
    $user = productionFixUserWithRole('purchasing-agent');

    expect($user->can('viewAny', PurchaseOrder::class))->toBeTrue()
        ->and($user->can('create', PurchaseOrder::class))->toBeTrue();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/procurement/purchase-orders/create')
        ->assertSuccessful();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/purchase-orders/create')
        ->assertSuccessful();
});

it('allows sales managers to open customer pages', function (): void {
    $user = productionFixUserWithRole('sales-manager');
    $customer = Customer::factory()->create();

    expect($user->can('viewAny', Customer::class))->toBeTrue()
        ->and($user->can('view', $customer))->toBeTrue()
        ->and($user->can('create', Customer::class))->toBeTrue()
        ->and($user->can('update', $customer))->toBeTrue();

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
        ->assertSuccessful();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get("/sales/customers/{$customer->getKey()}/edit")
        ->assertSuccessful();
});

it('does not auto-create an extra contact when a customer is created with an explicit contact', function (): void {
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::factory()->create();
    $customerPostingGroup = CustomerPostingGroup::factory()->create();
    $contact = Contact::factory()->create([
        'name' => 'Explicit Customer Contact',
    ]);
    $initialContactCount = Contact::query()->count();

    $customer = Customer::query()->create([
        'name' => 'No Contact Customer Ltd',
        'email' => 'no-contact-customer@example.test',
        'phone' => '+2348000000000',
        'general_business_posting_group_id' => $generalBusinessPostingGroup->getKey(),
        'customer_posting_group_id' => $customerPostingGroup->getKey(),
        'vat_bus_posting_group' => 'DOMESTIC',
        'blocked' => false,
        'credit_limit' => 0,
        'contact_id' => $contact->getKey(),
    ]);

    expect($customer->refresh()->contact_id)->toBe($contact->getKey())
        ->and(Contact::query()->count())->toBe($initialContactCount);
});

it('uses customer contact permissions for the Contact-backed customer contact resource', function (): void {
    $salesManager = productionFixUserWithRole('sales-manager');
    $customerOnlyUser = User::factory()->create();

    $customerOnlyUser->givePermissionTo([
        'sales.customer.view_any',
        'sales.customer.view',
        'sales.customer.create',
        'sales.customer.update',
    ]);

    expect(Gate::getPolicyFor(Contact::class))->toBeInstanceOf(ContactPolicy::class)
        ->and($salesManager->can('viewAny', Contact::class))->toBeTrue()
        ->and($salesManager->can('create', Contact::class))->toBeTrue()
        ->and($customerOnlyUser->can('create', Contact::class))->toBeFalse();
});
