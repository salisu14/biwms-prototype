<?php

declare(strict_types=1);

use App\Filament\Resources\Businesses\Pages\CreateBusiness;
use App\Filament\Resources\CompanyInformation\Pages\CreateCompanyInformation;
use App\Models\Allocation;
use App\Models\Business;
use App\Models\CompanyInformation;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Role;
use App\Models\User;
use App\Policies\GenericFilamentPolicy;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

function businessAuthorizationUserWithPermissions(array $permissions): User
{
    $role = Role::query()->create(['name' => 'business-create-test-role', 'guard_name' => 'web']);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

it('allows authorized users to create businesses through the real Filament create component', function (): void {
    Dimension::query()->create([
        'code' => 'BUSINESS',
        'name' => 'Business',
        'description' => 'Business dimension',
        'is_active' => true,
    ]);

    $user = businessAuthorizationUserWithPermissions([
        'businesses.business.view_any',
        'businesses.business.view',
        'businesses.business.create',
    ]);

    expect(Gate::forUser($user)->allows('create', Business::class))->toBeTrue()
        ->and($user->can('businesses.business.update'))->toBeFalse();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/businesses/create')
        ->assertSuccessful();

    Livewire::actingAs($user)
        ->test(CreateBusiness::class)
        ->fillForm([
            'code' => 'WEST',
            'name' => 'Western Operations',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $business = Business::query()->where('code', 'WEST')->first();

    expect($business)->not->toBeNull()
        ->and($business->name)->toBe('Western Operations')
        ->and(DimensionValue::query()->where('code', 'WEST')->exists())->toBeTrue();
});

it('denies Business creation to users without Business create permission', function (): void {
    $user = businessAuthorizationUserWithPermissions([
        'businesses.business.view_any',
        'businesses.business.view',
    ]);

    expect(Gate::forUser($user)->allows('create', Business::class))->toBeFalse();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/businesses/create')
        ->assertNotFound();
});

it('allows authorized users to create the linked Company Information profile for a Business', function (): void {
    $business = Business::query()->create([
        'code' => 'NORTH',
        'name' => 'Northern Operations',
        'is_active' => true,
    ]);

    $user = businessAuthorizationUserWithPermissions([
        'company_information.company_information.view_any',
        'company_information.company_information.view',
        'company_information.company_information.create',
    ]);

    expect(Gate::forUser($user)->allows('create', CompanyInformation::class))->toBeTrue()
        ->and($user->can('company_information.company_information.update'))->toBeFalse();

    $this->actingAs($user)
        ->withSession([
            'active_business_id' => $business->id,
            'two_factor_passed_at' => now()->timestamp,
        ])
        ->get('/admin/company-information/create')
        ->assertSuccessful();

    Livewire::actingAs($user)
        ->test(CreateCompanyInformation::class)
        ->fillForm([
            'business_id' => $business->id,
            'company_name' => 'Northern Operations Limited',
            'trading_name' => 'Northern Operations',
            'registration_no' => 'RC-1000',
            'tax_registration_no' => 'TIN-1000',
            'tax_office' => 'FIRS',
            'address_line_1' => '1 Enterprise Way',
            'city' => 'Lagos',
            'state_province' => 'Lagos',
            'country_code' => 'NGA',
            'phone_no' => '+234 800 000 0000',
            'email' => 'hello@example.test',
            'fiscal_year_start_month' => '01',
            'base_currency_code' => 'NGN',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CompanyInformation::query()
        ->where('business_id', $business->id)
        ->where('company_name', 'Northern Operations Limited')
        ->exists())->toBeTrue();
});

it('denies Company Information creation to users without create permission', function (): void {
    $business = Business::query()->create([
        'code' => 'EAST',
        'name' => 'Eastern Operations',
        'is_active' => true,
    ]);

    $user = businessAuthorizationUserWithPermissions([
        'company_information.company_information.view_any',
        'company_information.company_information.view',
    ]);

    expect(Gate::forUser($user)->allows('create', CompanyInformation::class))->toBeFalse();

    $this->actingAs($user)
        ->withSession([
            'active_business_id' => $business->id,
            'two_factor_passed_at' => now()->timestamp,
        ])
        ->get('/admin/company-information/create')
        ->assertNotFound();
});

it('resolves class-level create authorization for generic Filament policies without route context', function (): void {
    $user = businessAuthorizationUserWithPermissions([
        'allocations.allocation.view_any',
        'allocations.allocation.view',
        'allocations.allocation.create',
    ]);

    expect(Gate::getPolicyFor(Allocation::class))->toBeInstanceOf(GenericFilamentPolicy::class)
        ->and(Gate::forUser($user)->allows('create', Allocation::class))->toBeTrue();
});
