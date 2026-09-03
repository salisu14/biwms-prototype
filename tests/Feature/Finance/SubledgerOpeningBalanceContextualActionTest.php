<?php

declare(strict_types=1);

use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\SubledgerOpeningBalances\Pages\CreateSubledgerOpeningBalance;
use App\Filament\Resources\SubledgerOpeningBalances\SubledgerOpeningBalanceResource;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function contextualActionUser(array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]))->all());

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->refresh();
}

it('offers a customer opening balance shortcut with customer and business context', function (): void {
    $business = Business::query()->create(['code' => 'CTX-C', 'name' => 'Context Customer Business']);
    session(['active_business_id' => $business->id]);
    $customer = Customer::factory()->create();
    $user = contextualActionUser([
        'sales.customer.view_any',
        'sales.customer.view',
        'finance.customer_opening_balance.create',
    ]);

    Livewire::actingAs($user)
        ->test(ViewCustomer::class, ['record' => $customer->getKey()])
        ->assertSee('Enter Opening Balance')
        ->assertSee('party_type=CUSTOMER')
        ->assertSee('party_id='.$customer->getKey())
        ->assertSee('business_id='.$business->id);
});

it('offers a vendor opening balance shortcut only with vendor opening permission', function (): void {
    $business = Business::query()->create(['code' => 'CTX-V', 'name' => 'Context Vendor Business']);
    session(['active_business_id' => $business->id]);
    $vendor = Vendor::factory()->create();
    $vendorUser = contextualActionUser([
        'procurement.vendor.view_any',
        'procurement.vendor.view',
        'finance.vendor_opening_balance.create',
    ]);

    Livewire::actingAs($vendorUser)
        ->test(ViewVendor::class, ['record' => $vendor->getKey()])
        ->assertSee('Enter Opening Balance')
        ->assertSee('party_type=VENDOR')
        ->assertSee('party_id='.$vendor->getKey())
        ->assertSee('business_id='.$business->id);

    $customerUser = contextualActionUser([
        'procurement.vendor.view_any',
        'procurement.vendor.view',
        'finance.customer_opening_balance.create',
    ]);

    Livewire::actingAs($customerUser)
        ->test(ViewVendor::class, ['record' => $vendor->getKey()])
        ->assertDontSee('Enter Opening Balance');
});

it('renders the generic and contextual subledger opening balance create pages', function (): void {
    $business = Business::query()->create(['code' => 'RENDER', 'name' => 'Render Test Business']);
    session(['active_business_id' => $business->id]);
    $customer = Customer::factory()->create();
    $vendor = Vendor::factory()->create(['currency' => 'USD']);
    $user = contextualActionUser([
        'finance.subledger_opening_balance.view_any',
        'finance.subledger_opening_balance.create',
    ]);

    foreach ([
        [],
        ['party_type' => 'CUSTOMER', 'party_id' => $customer->getKey(), 'business_id' => $business->getKey()],
        ['party_type' => 'VENDOR', 'party_id' => $vendor->getKey(), 'business_id' => $business->getKey()],
    ] as $parameters) {
        $url = SubledgerOpeningBalanceResource::getUrl(
            'create',
            panel: 'admin',
            parameters: $parameters,
        );
        $parsedUrl = parse_url($url);
        $path = ($parsedUrl['path'] ?? '/').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');

        $this->actingAs($user)->get($path)->assertOk();
    }

    expect(class_exists(CreateSubledgerOpeningBalance::class))->toBeTrue();
});

it('renders the subledger opening balance index with the Filament 5 table actions', function (): void {
    $business = Business::query()->create(['code' => 'INDEX', 'name' => 'Index Test Business']);
    session(['active_business_id' => $business->id]);
    $user = contextualActionUser([
        'finance.subledger_opening_balance.view_any',
    ]);

    $this->actingAs($user)
        ->get('/admin/subledger-opening-balances')
        ->assertOk();
});
