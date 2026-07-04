<?php

use App\Filament\Pages\Finance\ProfitAndLossReport;
use App\Filament\Pages\Manufacturing\ProductionPerformanceReport;
use App\Filament\Pages\Manufacturing\WipValuationReport;
use App\Filament\Resources\AuditTrails\AuditTrailResource;
use App\Filament\Resources\ChartOfAccounts\ChartOfAccountResource;
use App\Filament\Resources\NumberSeries\NumberSeriesResource;
use App\Filament\Resources\ProductionBoms\ProductionBomResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditTrail;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\ProductionOrderPolicy;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolePermissionSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('allows Super Admin to manage roles while ordinary users cannot see RoleResource', function (): void {
    $superAdmin = User::factory()->create();
    $ordinaryUser = User::factory()->create();

    $superAdmin->givePermissionTo('role_permission.manage');

    $this->actingAs($superAdmin);
    expect(RoleResource::canViewAny())->toBeTrue();

    $this->actingAs($ordinaryUser);
    expect(RoleResource::canViewAny())->toBeFalse()
        ->and(UserResource::canViewAny())->toBeFalse();
});

it('defaults roles and permissions to web guard and labels dangerous permissions', function (): void {
    $role = Role::query()->create(['name' => 'daily-operator']);

    expect($role->guard_name)->toBe('web')
        ->and(RoleResource::permissionGroupFor('factory.production_order.view_any'))->toBe('Manufacturing')
        ->and(RoleResource::permissionGroupFor('view:any:sales_quote'))->toBe('Legacy')
        ->and(RoleResource::isDangerousPermission('role_permission.manage'))->toBeTrue()
        ->and(RoleResource::permissionLabelFor('finance.payment.post'))->toContain('Finance Payment Post')
        ->and(RoleResource::permissionLabelFor('role_permission.manage'))->toContain('[DANGEROUS]');
});

it('audits permission grants and revocations', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo('role_permission.manage');
    $role = Role::query()->create(['name' => 'audited-role']);

    $this->actingAs($admin);

    $role->givePermissionTo('finance.payment.view_any');
    $role->revokePermissionTo('finance.payment.view_any');

    expect(AuditTrail::query()->where('event_type', 'permission')->where('action', 'permission_granted')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('event_type', 'permission')->where('action', 'permission_revoked')->exists())->toBeTrue();
});

it('applies least privilege defaults for daily role presets', function (): void {
    $this->seed(RolePermissionSetSeeder::class);

    $manufacturingOperator = User::factory()->create();
    $financeUser = User::factory()->create();
    $payrollUser = User::factory()->create();

    $manufacturingOperator->assignRole('factory-operator');
    $financeUser->assignRole('finance-accountant');
    $payrollUser->assignRole('hr-officer');

    $this->actingAs($manufacturingOperator);
    expect(ProductionOrderResource::canViewAny())->toBeTrue()
        ->and(ProductionBomResource::canViewAny())->toBeFalse()
        ->and(ProductionPerformanceReport::canAccess())->toBeFalse()
        ->and(WipValuationReport::canAccess())->toBeFalse();

    $this->actingAs($financeUser);
    expect(ProfitAndLossReport::canAccess())->toBeTrue()
        ->and($financeUser->can('hr.payroll_document.view_any'))->toBeFalse()
        ->and(NumberSeriesResource::canViewAny())->toBeFalse();

    $this->actingAs($payrollUser);
    expect($payrollUser->can('hr.payroll_document.view_any'))->toBeTrue()
        ->and($payrollUser->can('finance.payment.view_any'))->toBeFalse()
        ->and(ChartOfAccountResource::canViewAny())->toBeFalse()
        ->and(AuditTrailResource::canViewAny())->toBeFalse();
});

it('returns 404 for restricted Filament URLs and allows authorized role URLs', function (): void {
    $ordinaryUser = User::factory()->create();
    $securityAdmin = User::factory()->create();
    $securityAdmin->givePermissionTo('role_permission.manage');

    $this->actingAs($ordinaryUser)
        ->get('/admin/roles')
        ->assertNotFound();

    $this->actingAs($securityAdmin)
        ->get('/admin/roles')
        ->assertSuccessful();
});

it('mounts role edit permissions with a bounded query count for large permission sets', function (): void {
    $securityAdmin = User::factory()->create();
    $securityAdmin->givePermissionTo('role_permission.manage');

    $role = Role::query()->create(['name' => 'large-permission-role']);

    $now = now();
    $bulkPermissions = collect(range(1, 1500))
        ->map(fn (int $index): array => [
            'name' => 'factory.synthetic_resource_'.$index.'.view',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ])
        ->all();

    Permission::query()->insert($bulkPermissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    RoleResource::clearPermissionCatalog();

    $queryCount = 0;

    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    Livewire::actingAs($securityAdmin)
        ->test(ManageRoles::class)
        ->mountTableAction('edit', $role);

    expect($queryCount)->toBeLessThan(80);
});

it('syncs role permissions from grouped edit form state', function (): void {
    $securityAdmin = User::factory()->create();
    $securityAdmin->givePermissionTo('role_permission.manage');

    $role = Role::query()->create(['name' => 'editable-role']);
    $financePermission = Permission::query()->where('name', 'finance.payment.view_any')->firstOrFail();
    $salesPermission = Permission::query()->where('name', 'sales.sales_order.view_any')->firstOrFail();

    RoleResource::clearPermissionCatalog();

    Livewire::actingAs($securityAdmin)
        ->test(ManageRoles::class)
        ->callTableAction('edit', $role, data: [
            'name' => 'edited-role',
            'guard_name' => 'web',
            'permission_groups' => [
                'finance' => [$financePermission->id],
                'sales' => [$salesPermission->id],
            ],
            SensitiveActionPasswordConfirmation::FIELD => 'password',
        ])
        ->assertHasNoTableActionErrors();

    expect($role->fresh()->name)->toBe('edited-role')
        ->and($role->fresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing([
            'finance.payment.view_any',
            'sales.sales_order.view_any',
        ]);
});

it('protects sensitive posting actions with backend policies', function (): void {
    $user = User::factory()->create();
    $order = new ProductionOrder;

    expect(app(ProductionOrderPolicy::class)->postOutput($user, $order))->toBeFalse()
        ->and(app(ProductionOrderPolicy::class)->finish($user, $order))->toBeFalse();

    $user->givePermissionTo('factory.production_order.post_output');
    expect(app(ProductionOrderPolicy::class)->postOutput($user, $order))->toBeTrue()
        ->and(app(ProductionOrderPolicy::class)->finish($user, $order))->toBeFalse();

    $user->givePermissionTo('factory.production_order.finish');
    expect(app(ProductionOrderPolicy::class)->finish($user, $order))->toBeTrue();
});
