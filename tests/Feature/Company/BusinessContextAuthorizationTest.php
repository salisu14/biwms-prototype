<?php

use App\Models\AuditTrail;
use App\Models\Business;
use App\Models\CompanyInformation;
use App\Models\User;
use App\Models\UserBusiness;
use App\Services\Business\BusinessContextService;
use App\Services\Business\BusinessEntitlementService;
use App\Services\Company\CompanyInformationService;
use App\Services\Dashboard\FinanceDashboardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows an explicitly entitled user to select a business', function (): void {
    $user = User::factory()->create();
    $business = Business::query()->create(['code' => 'BIZ-A', 'name' => 'Business A', 'is_active' => true]);
    UserBusiness::query()->create(['user_id' => $user->id, 'business_id' => $business->id]);

    auth()->login($user);

    expect(app(BusinessContextService::class)->setActive($business->id)->is($business))
        ->toBeTrue()
        ->and(session('active_business_id'))->toBe($business->id);
});

it('rejects an unauthorized explicit business id and does not change the session', function (): void {
    $user = User::factory()->create();
    $businessA = Business::query()->create(['code' => 'BIZ-A', 'name' => 'Business A', 'is_active' => true]);
    $businessB = Business::query()->create(['code' => 'BIZ-B', 'name' => 'Business B', 'is_active' => true]);
    UserBusiness::query()->create(['user_id' => $user->id, 'business_id' => $businessA->id]);
    session(['active_business_id' => $businessA->id]);
    auth()->login($user);

    expect(fn () => app(BusinessContextService::class)->setActive($businessB->id))
        ->toThrow(AuthorizationException::class)
        ->and(session('active_business_id'))->toBe($businessA->id);
});

it('rejects inactive and stale session businesses', function (): void {
    $user = User::factory()->create();
    $business = Business::query()->create(['code' => 'BIZ-INACTIVE', 'name' => 'Inactive', 'is_active' => false]);
    UserBusiness::query()->create(['user_id' => $user->id, 'business_id' => $business->id]);
    auth()->login($user);

    expect(fn () => app(BusinessContextService::class)->setActive($business->id))
        ->toThrow(AuthorizationException::class);

    session(['active_business_id' => $business->id]);
    expect(fn () => app(BusinessContextService::class)->resolve())
        ->toThrow(AuthorizationException::class);
});

it('gives Super Admin explicit access to every active business only', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $active = Business::query()->create(['code' => 'BIZ-A', 'name' => 'Business A', 'is_active' => true]);
    $inactive = Business::query()->create(['code' => 'BIZ-B', 'name' => 'Business B', 'is_active' => false]);
    auth()->login($user);

    expect(app(BusinessContextService::class)->authorizedBusinesses())
        ->toHaveCount(1)
        ->and(fn () => app(BusinessContextService::class)->setActive($active->id))->not->toThrow(Throwable::class)
        ->and(fn () => app(BusinessContextService::class)->setActive($inactive->id))->toThrow(AuthorizationException::class);
});

it('prevents an unauthorized user from resolving another business company header', function (): void {
    $user = User::factory()->create();
    $businessA = Business::query()->create(['code' => 'BIZ-A', 'name' => 'Business A', 'is_active' => true]);
    $businessB = Business::query()->create(['code' => 'BIZ-B', 'name' => 'Business B', 'is_active' => true]);
    UserBusiness::query()->create(['user_id' => $user->id, 'business_id' => $businessA->id]);
    CompanyInformation::query()->create(['business_id' => $businessA->id, 'company_name' => 'Company A', 'country_code' => 'NGA']);
    CompanyInformation::query()->create(['business_id' => $businessB->id, 'company_name' => 'Company B', 'country_code' => 'NGA']);
    auth()->login($user);

    expect(fn () => app(CompanyInformationService::class)->getReportHeader($businessB->id))
        ->toThrow(AuthorizationException::class);
});

it('does not create company information while reading a missing report profile', function (): void {
    $user = User::factory()->create();
    $business = Business::query()->create(['code' => 'BIZ-MISSING', 'name' => 'Missing', 'is_active' => true]);
    UserBusiness::query()->create(['user_id' => $user->id, 'business_id' => $business->id]);
    auth()->login($user);
    $before = CompanyInformation::query()->count();

    expect(fn () => app(CompanyInformationService::class)->getReportHeader($business->id))
        ->toThrow(RuntimeException::class)
        ->and(CompanyInformation::query()->count())->toBe($before);
});

it('audits authorized business access grants and revocations', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $user = User::factory()->create();
    $business = Business::query()->create(['code' => 'BIZ-AUDIT', 'name' => 'Audit Business', 'is_active' => true]);
    auth()->login($admin);

    $service = app(BusinessEntitlementService::class);
    $service->grant($user, $business, $admin->id);
    $service->revoke($user, $business, $admin->id);

    expect(AuditTrail::query()->where('action', 'business_access_granted')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('action', 'business_access_revoked')->exists())->toBeTrue();
});

it('rejects an unauthorized dashboard business override', function (): void {
    $user = User::factory()->create();
    $businessA = Business::query()->create(['code' => 'BIZ-A', 'name' => 'Business A', 'is_active' => true]);
    $businessB = Business::query()->create(['code' => 'BIZ-B', 'name' => 'Business B', 'is_active' => true]);
    UserBusiness::query()->create(['user_id' => $user->id, 'business_id' => $businessA->id]);
    auth()->login($user);

    expect(fn () => app(FinanceDashboardService::class)->summary(businessId: $businessB->id))
        ->toThrow(AuthorizationException::class);
});

it('resolves an owned document business before active session context', function (): void {
    $user = User::factory()->create();
    $businessA = Business::query()->create(['code' => 'BIZ-A', 'name' => 'Business A', 'is_active' => true]);
    $businessB = Business::query()->create(['code' => 'BIZ-B', 'name' => 'Business B', 'is_active' => true]);
    UserBusiness::query()->insert([
        ['user_id' => $user->id, 'business_id' => $businessA->id],
        ['user_id' => $user->id, 'business_id' => $businessB->id],
    ]);
    auth()->login($user);
    session(['active_business_id' => $businessB->id]);

    expect(app(CompanyInformationService::class)->resolveOwnedBusinessId(ownedBusiness: $businessA))
        ->toBe($businessA->id);
});
