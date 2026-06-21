<?php

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\IdentityAndAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('manufacturing reports render', function () {
    $this->seed(IdentityAndAccessSeeder::class);

    $user = User::factory()->create();
    Permission::query()->firstOrCreate([
        'name' => 'factory.report.view',
        'guard_name' => 'web',
    ]);
    $user->givePermissionTo('factory.report.view');

    $this->actingAs($user)
        ->get('/admin/wip-valuation-report')
        ->assertOk();

    $this->actingAs($user)
        ->get('/admin/production-performance-report')
        ->assertOk();
});
