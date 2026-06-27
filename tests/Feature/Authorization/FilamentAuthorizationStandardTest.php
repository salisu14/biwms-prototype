<?php

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditTrail;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\FilamentPermissionRegistry;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('seeds standard admin user permissions without old generated names', function (): void {
    $expectedPermissions = [
        'admin.user.view_any',
        'admin.user.view',
        'admin.user.create',
        'admin.user.update',
        'admin.user.delete',
        'admin.user.delete_any',
        'admin.user.restore',
        'admin.user.restore_any',
        'admin.user.force_delete',
        'admin.user.force_delete_any',
    ];

    foreach ($expectedPermissions as $permission) {
        expect(Permission::query()->where('name', $permission)->exists())->toBeTrue();
    }

    expect(Permission::query()->where('name', 'view_any_user')->exists())->toBeFalse();
});

it('registers policies and permission metadata for every Filament resource', function (): void {
    $registry = app(FilamentPermissionRegistry::class);

    foreach ($registry->resources() as $resourceClass) {
        expect(method_exists($resourceClass, 'permissionModule'))->toBeTrue($resourceClass.' is missing permissionModule().')
            ->and(method_exists($resourceClass, 'permissionResource'))->toBeTrue($resourceClass.' is missing permissionResource().');

        if (method_exists($resourceClass, 'getModel')) {
            $policy = Gate::getPolicyFor($resourceClass::getModel());

            expect($policy)->not->toBeNull($resourceClass.' has no registered policy.');

            foreach (['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'] as $method) {
                expect(method_exists($policy, $method))->toBeTrue($resourceClass.' policy is missing '.$method.'().');
            }
        }
    }
});

it('protects user management with the standard UserPolicy', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $superAdminRole = Role::query()->create(['name' => 'super_admin']);

    $target->assignRole($superAdminRole);

    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf(UserPolicy::class)
        ->and(app(UserPolicy::class)->delete($actor, $actor))->toBeFalse()
        ->and(app(UserPolicy::class)->update($actor, $target))->toBeFalse();

    $actor->givePermissionTo('admin.user.view_any');
    $this->actingAs($actor);

    expect(UserResource::canViewAny())->toBeTrue();
});

it('audits role assignment changes', function (): void {
    $admin = User::factory()->create();
    $role = Role::query()->create(['name' => 'audited-security-role']);

    $this->actingAs($admin);

    $admin->assignRole($role);

    expect(AuditTrail::query()
        ->where('event_type', 'permission')
        ->where('action', 'role_assigned')
        ->where('auditable_type', $admin->getMorphClass())
        ->where('auditable_id', $admin->getKey())
        ->exists())->toBeTrue();
});

it('reports a clean strict authorization audit after permission seeding', function (): void {
    expect(Artisan::call('biwms:security-audit', ['--json' => true]))->toBe(0);

    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['resources_without_permission_module'])->toBe([])
        ->and($report['resources_without_permission_resource'])->toBe([])
        ->and($report['resources_without_policies'])->toBe([])
        ->and($report['missing_generated_permissions'])->toBe([])
        ->and($report['wrong_pattern_permissions'])->toBe([]);
});
