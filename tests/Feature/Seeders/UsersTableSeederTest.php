<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates one linked employee user for each seeded role', function (): void {
    $this->seed(RolesTableSeeder::class);
    $this->seed(UsersTableSeeder::class);

    $roles = Role::query()
        ->where('guard_name', 'web')
        ->get();

    $missingRoleUsers = $roles
        ->filter(fn (Role $role): bool => ! User::query()
            ->whereHas('roles', fn ($query) => $query->where('roles.id', $role->id))
            ->whereNotNull('employee_id')
            ->whereHas('employee')
            ->exists()
        )
        ->pluck('name')
        ->values();

    expect($roles)->toHaveCount(17)
        ->and($missingRoleUsers)->toBeEmpty()
        ->and(User::query()->whereHas('roles')->whereHas('employee')->count())->toBe(17)
        ->and(User::query()->where('email', 'sadmin@admin.com')->whereHas('employee')->exists())->toBeTrue();
});
