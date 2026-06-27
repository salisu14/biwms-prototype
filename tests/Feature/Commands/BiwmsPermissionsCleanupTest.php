<?php

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('defaults to dry run and does not delete eligible old generated permissions', function (): void {
    $permissionName = 'view_any_'.'cleanup_target';

    Permission::query()->create([
        'name' => $permissionName,
        'guard_name' => 'web',
    ]);

    expect(Artisan::call('biwms:permissions-cleanup'))->toBe(0)
        ->and(Artisan::output())->toContain('Dry run')
        ->and(Permission::query()->where('name', $permissionName)->exists())->toBeTrue();
});

it('deletes eligible old generated permissions only when forced', function (): void {
    $permissionName = 'delete_'.'cleanup_target';

    Permission::query()->create([
        'name' => $permissionName,
        'guard_name' => 'web',
    ]);

    expect(Artisan::call('biwms:permissions-cleanup', ['--force' => true]))->toBe(0)
        ->and(Permission::query()->where('name', $permissionName)->exists())->toBeFalse();
});

it('protects old generated permissions that are still referenced in code', function (): void {
    $permissionName = 'view_any_price_list';

    Permission::query()->create([
        'name' => $permissionName,
        'guard_name' => 'web',
    ]);

    expect(Artisan::call('biwms:permissions-cleanup', ['--force' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('referenced in code')
        ->and(Permission::query()->where('name', $permissionName)->exists())->toBeTrue();
});
