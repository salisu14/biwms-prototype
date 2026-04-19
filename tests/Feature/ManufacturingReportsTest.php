<?php

use App\Models\User;
use Database\Seeders\IdentityAndAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('manufacturing reports render', function () {
    $user = User::factory()->create();

    $this->seed(IdentityAndAccessSeeder::class);

    $this->actingAs($user)
        ->get('/admin/wip-valuation-report')
        ->assertOk();

    $this->actingAs($user)
        ->get('/admin/production-performance-report')
        ->assertOk();
});
