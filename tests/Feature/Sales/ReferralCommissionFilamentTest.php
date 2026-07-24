<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Permission;
use App\Models\ReferralCommissionPlan;
use App\Models\ReferralCommissionSetting;
use App\Models\ReferrerCommissionPlanAssignment;
use App\Models\Role;
use App\Models\User;
use App\Policies\ReferralCommissionPlanPolicy;
use App\Policies\ReferralCommissionSettingPolicy;
use App\Policies\ReferrerCommissionPlanAssignmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    referralCommissionFilamentRole('sales-manager', [
        'sales.referral_commission_setting.view_any',
        'sales.referral_commission_setting.view',
        'sales.referral_commission_setting.create',
        'sales.referral_commission_setting.update',
        'sales.referral_commission_setting.manage',
        'sales.referral_commission_plan.view_any',
        'sales.referral_commission_plan.view',
        'sales.referral_commission_plan.create',
        'sales.referral_commission_plan.update',
        'sales.referral_commission_plan.activate',
        'sales.referral_commission_plan.inactivate',
        'sales.referrer_commission_plan_assignment.view_any',
        'sales.referrer_commission_plan_assignment.view',
        'sales.referrer_commission_plan_assignment.create',
        'sales.referrer_commission_plan_assignment.assign',
        'sales.referrer_commission_plan_assignment.change',
        'sales.referrer_commission_plan_assignment.end',
        'sales.referrer_commission_plan_assignment.cancel',
        'sales.referrer.view_any',
        'sales.referrer.view',
    ]);

    referralCommissionFilamentRole('sales-representative', [
        'sales.referral_commission_plan.view_any',
        'sales.referral_commission_plan.view',
        'sales.referrer_commission_plan_assignment.view_any',
        'sales.referrer_commission_plan_assignment.view',
    ]);
});

function referralCommissionFilamentRole(string $roleName, array $permissions): Role
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

function referralCommissionFilamentUser(string $roleName): User
{
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole(Role::query()->where('name', $roleName)->firstOrFail());

    return $user;
}

it('renders referral commission resources in admin and sales panels', function (): void {
    $user = referralCommissionFilamentUser('sales-manager');
    $plan = ReferralCommissionPlan::factory()->active()->create();
    $assignment = ReferrerCommissionPlanAssignment::factory()->create([
        'referral_commission_plan_id' => $plan->id,
    ]);
    $setting = ReferralCommissionSetting::query()->create([
        'business_id' => Business::query()->create(['code' => 'BIZ-FIL', 'name' => 'Filament Business', 'is_active' => true])->id,
        'commission_decimal_places' => 4,
    ]);

    foreach ([
        '/admin/referral-commission-settings',
        '/admin/referral-commission-settings/create',
        "/admin/referral-commission-settings/{$setting->id}",
        "/admin/referral-commission-settings/{$setting->id}/edit",
        '/admin/referral-commission-plans',
        '/admin/referral-commission-plans/create',
        "/admin/referral-commission-plans/{$plan->id}",
        "/admin/referral-commission-plans/{$plan->id}/edit",
        '/admin/referrer-commission-plan-assignments',
        '/admin/referrer-commission-plan-assignments/create',
        "/admin/referrer-commission-plan-assignments/{$assignment->id}",
        "/admin/referrers/{$assignment->referrer_id}",
        '/sales/referral-commission-settings',
        '/sales/referral-commission-plans',
        '/sales/referrer-commission-plan-assignments',
    ] as $url) {
        $this->actingAs($user)
            ->withSession(['two_factor_passed_at' => now()->timestamp])
            ->get($url)
            ->assertSuccessful();
    }
});

it('resolves policies and keeps assignment workflow permissions narrow', function (): void {
    $manager = referralCommissionFilamentUser('sales-manager');
    $salesperson = referralCommissionFilamentUser('sales-representative');
    $plan = ReferralCommissionPlan::factory()->create();
    $setting = ReferralCommissionSetting::query()->create([
        'business_id' => Business::query()->create(['code' => 'BIZ-POL', 'name' => 'Policy Business', 'is_active' => true])->id,
        'commission_decimal_places' => 4,
    ]);
    $assignment = ReferrerCommissionPlanAssignment::factory()->create();

    expect(Gate::getPolicyFor(ReferralCommissionSetting::class))->toBeInstanceOf(ReferralCommissionSettingPolicy::class)
        ->and(Gate::getPolicyFor(ReferralCommissionPlan::class))->toBeInstanceOf(ReferralCommissionPlanPolicy::class)
        ->and(Gate::getPolicyFor(ReferrerCommissionPlanAssignment::class))->toBeInstanceOf(ReferrerCommissionPlanAssignmentPolicy::class)
        ->and($manager->can('manage', $setting))->toBeTrue()
        ->and($manager->can('activate', $plan))->toBeTrue()
        ->and($manager->can('change', $assignment))->toBeTrue()
        ->and($salesperson->can('view', $plan))->toBeTrue()
        ->and($salesperson->can('activate', $plan))->toBeFalse()
        ->and($salesperson->can('change', $assignment))->toBeFalse();
});
