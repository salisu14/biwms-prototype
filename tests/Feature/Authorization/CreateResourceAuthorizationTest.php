<?php

declare(strict_types=1);

use App\Filament\Resources\UnitOfMeasures\Pages\CreateUnitOfMeasure;
use App\Filament\Resources\WorkCenterCalendars\Pages\CreateWorkCenterCalendar;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterCalendar;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

function createResourceAuthorizationSuperAdmin(): User
{
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);

    $user->assignRole('super_admin');

    return $user;
}

function createResourceAuthorizationUserWithPermissions(array $permissions): User
{
    $role = Role::query()->create(['name' => 'create-only-admin', 'guard_name' => 'web']);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('generates the expected permissions for unit of measure and work center calendar resources', function (): void {
    expect(Permission::query()->where('name', 'unit_of_measures.unit_of_measure.create')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'unit_of_measures.unit_of_measure.update')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'factory.work_center_calendar.create')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'factory.work_center_calendar.update')->exists())->toBeTrue();
});

it('allows a Super Admin to open and create units of measure', function (): void {
    $superAdmin = createResourceAuthorizationSuperAdmin();

    $this->actingAs($superAdmin)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/unit-of-measures/create')
        ->assertSuccessful();

    Livewire::actingAs($superAdmin)
        ->test(CreateUnitOfMeasure::class)
        ->fillForm([
            'uom_code' => 'BOX',
            'description' => 'Box',
            'conversion_factor' => 12,
            'is_base_uom' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(UnitOfMeasure::query()->where('uom_code', 'BOX')->exists())->toBeTrue()
        ->and((float) UnitOfMeasure::query()->where('uom_code', 'BOX')->value('conversion_factor'))->toBe(12.0);
});

it('does not require update permission during unit of measure creation', function (): void {
    $user = createResourceAuthorizationUserWithPermissions([
        'unit_of_measures.unit_of_measure.view_any',
        'unit_of_measures.unit_of_measure.view',
        'unit_of_measures.unit_of_measure.create',
    ]);

    expect($user->can('unit_of_measures.unit_of_measure.create'))->toBeTrue()
        ->and($user->can('unit_of_measures.unit_of_measure.update'))->toBeFalse();

    Livewire::actingAs($user)
        ->test(CreateUnitOfMeasure::class)
        ->fillForm([
            'uom_code' => 'PAL',
            'description' => 'Pallet',
            'conversion_factor' => 48,
            'is_base_uom' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(UnitOfMeasure::query()->where('uom_code', 'PAL')->exists())->toBeTrue()
        ->and((float) UnitOfMeasure::query()->where('uom_code', 'PAL')->value('conversion_factor'))->toBe(48.0);
});

it('allows a Super Admin to open and create work center calendars', function (): void {
    $superAdmin = createResourceAuthorizationSuperAdmin();
    $workCenter = WorkCenter::factory()->create();

    $this->actingAs($superAdmin)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/work-center-calendars/create')
        ->assertSuccessful();

    Livewire::actingAs($superAdmin)
        ->test(CreateWorkCenterCalendar::class)
        ->fillForm([
            'work_center_id' => $workCenter->id,
            'date' => '2026-07-09',
            'is_working_day' => true,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'capacity' => 8,
            'efficiency' => 100,
            'absence_code' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WorkCenterCalendar::query()
        ->where('work_center_id', $workCenter->id)
        ->whereDate('date', '2026-07-09')
        ->exists())->toBeTrue();
});

it('does not require update permission during work center calendar creation', function (): void {
    $user = createResourceAuthorizationUserWithPermissions([
        'factory.work_center_calendar.view_any',
        'factory.work_center_calendar.view',
        'factory.work_center_calendar.create',
    ]);
    $workCenter = WorkCenter::factory()->create();

    expect($user->can('factory.work_center_calendar.create'))->toBeTrue()
        ->and($user->can('factory.work_center_calendar.update'))->toBeFalse();

    Livewire::actingAs($user)
        ->test(CreateWorkCenterCalendar::class)
        ->fillForm([
            'work_center_id' => $workCenter->id,
            'date' => '2026-07-10',
            'is_working_day' => true,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'capacity' => 8,
            'efficiency' => 100,
            'absence_code' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WorkCenterCalendar::query()
        ->where('work_center_id', $workCenter->id)
        ->whereDate('date', '2026-07-10')
        ->exists())->toBeTrue();
});

it('hides create pages from users without create permission', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/unit-of-measures/create')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/admin/work-center-calendars/create')
        ->assertNotFound();
});
