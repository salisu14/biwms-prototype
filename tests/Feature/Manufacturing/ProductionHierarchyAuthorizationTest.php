<?php

declare(strict_types=1);

use App\Enums\ProductionHierarchyStatus;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionHierarchyNode;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantManufacturingPermission(User $user, string $permission): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::query()->firstOrCreate([
        'name' => $permission,
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
}

it('authorizes production hierarchy permissions explicitly', function (): void {
    $user = User::factory()->create();
    $hierarchy = ProductionHierarchy::factory()->create();

    expect(Gate::forUser($user)->allows('viewAny', ProductionHierarchy::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('create', ProductionHierarchy::class))->toBeFalse();

    grantManufacturingPermission($user, 'manufacturing.production_hierarchy.view_any');
    grantManufacturingPermission($user, 'manufacturing.production_hierarchy.create');
    grantManufacturingPermission($user, 'manufacturing.production_hierarchy.explode');

    expect(Gate::forUser($user)->allows('viewAny', ProductionHierarchy::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', ProductionHierarchy::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('explode', $hierarchy))->toBeTrue();
});

it('does not allow terminal hierarchy updates even with update permission', function (): void {
    $user = User::factory()->create();
    grantManufacturingPermission($user, 'manufacturing.production_hierarchy.update');

    $hierarchy = ProductionHierarchy::factory()->create(['status' => ProductionHierarchyStatus::Completed]);

    expect(Gate::forUser($user)->allows('update', $hierarchy))->toBeFalse();
});

it('keeps hierarchy nodes read only except view permissions', function (): void {
    $user = User::factory()->create();
    $node = ProductionHierarchyNode::factory()->create();
    grantManufacturingPermission($user, 'manufacturing.production_hierarchy_node.view_any');
    grantManufacturingPermission($user, 'manufacturing.production_hierarchy_node.view');

    expect(Gate::forUser($user)->allows('viewAny', ProductionHierarchyNode::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $node))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', ProductionHierarchyNode::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $node))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $node))->toBeFalse();
});

it('authorizes planned supply link updates and reservation release only with special permissions', function (): void {
    $user = User::factory()->create();
    $link = ProductionOrderSupplyLink::factory()->create();
    $reservation = ProductionMaterialReservation::factory()->create();

    expect(Gate::forUser($user)->allows('updatePlanned', $link))->toBeFalse()
        ->and(Gate::forUser($user)->allows('release', $reservation))->toBeFalse();

    grantManufacturingPermission($user, 'manufacturing.production_supply_link.update_planned');
    grantManufacturingPermission($user, 'manufacturing.production_material_reservation.release');

    expect(Gate::forUser($user)->allows('updatePlanned', $link))->toBeTrue()
        ->and(Gate::forUser($user)->allows('release', $reservation))->toBeTrue();
});
