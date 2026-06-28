<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\UserSecurity;
use App\Models\User;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'user_security.view',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'user_security.manage',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'role_permission.manage',
            'guard_name' => 'web',
        ]);
    }

    public function test_super_admin_can_access_page(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $superAdmin->forceFill([
            'two_factor_secret' => 'secret',
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($superAdmin)
            ->withSession(['two_factor_passed_at' => now()->timestamp])
            ->get('/admin/user-security')
            ->assertSuccessful()
            ->assertSee('User Security');
    }

    public function test_unauthorized_user_receives_404(): void
    {
        $regularUser = User::factory()->create();
        $regularUser->assignRole('user');

        $this->actingAs($regularUser)
            ->get('/admin/user-security')
            ->assertNotFound();
    }

    public function test_user_with_view_permission_can_access_page(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('user_security.view');

        $this->actingAs($user)
            ->get('/admin/user-security')
            ->assertSuccessful();
    }

    public function test_require_two_factor_action_works(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $targetUser = User::factory()->create([
            'two_factor_required' => false,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(UserSecurity::class)
            ->callTableAction('require_two_factor', $targetUser, data: [
                SensitiveActionPasswordConfirmation::FIELD => 'password',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'two_factor_required' => true,
        ]);

        $this->assertDatabaseHas('audit_trails', [
            'event_type' => 'security',
            'action' => 'two_factor_required',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
        ]);
    }

    public function test_force_reset_action_works(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $targetUser = User::factory()->create([
            'two_factor_secret' => 'some-secret',
            'two_factor_recovery_codes' => ['hashed-code'],
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::actingAs($superAdmin)
            ->test(UserSecurity::class)
            ->callTableAction('force_reset', $targetUser, data: [
                SensitiveActionPasswordConfirmation::FIELD => 'password',
            ])
            ->assertHasNoTableActionErrors();

        $targetUser->refresh();

        $this->assertNull($targetUser->two_factor_secret);
        $this->assertNull($targetUser->two_factor_recovery_codes);
        $this->assertNull($targetUser->two_factor_confirmed_at);
    }

    public function test_disable_two_factor_requires_email_confirmation(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $targetUser = User::factory()->create([
            'email' => 'target@example.com',
            'two_factor_secret' => 'some-secret',
            'two_factor_recovery_codes' => ['hashed-code'],
            'two_factor_confirmed_at' => now(),
            'two_factor_required' => false,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(UserSecurity::class)
            ->callTableAction('disable_two_factor', $targetUser, data: [
                'confirmation' => 'wrong@example.com',
                SensitiveActionPasswordConfirmation::FIELD => 'password',
            ])
            ->assertHasTableActionErrors(['confirmation']);

        $this->assertNotNull($targetUser->fresh()->two_factor_secret);
    }

    public function test_disable_two_factor_action_works_with_correct_confirmation(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $targetUser = User::factory()->create([
            'email' => 'target@example.com',
            'two_factor_secret' => 'some-secret',
            'two_factor_recovery_codes' => ['hashed-code'],
            'two_factor_confirmed_at' => now(),
            'two_factor_required' => false,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(UserSecurity::class)
            ->callTableAction('disable_two_factor', $targetUser, data: [
                'confirmation' => 'target@example.com',
                SensitiveActionPasswordConfirmation::FIELD => 'password',
            ])
            ->assertHasNoTableActionErrors();

        $targetUser->refresh();

        $this->assertNull($targetUser->two_factor_secret);
        $this->assertNull($targetUser->two_factor_confirmed_at);
    }

    public function test_required_two_factor_account_cannot_be_disabled(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $targetUser = User::factory()->create([
            'email' => 'required@example.com',
            'two_factor_secret' => 'some-secret',
            'two_factor_confirmed_at' => now(),
            'two_factor_required' => true,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(UserSecurity::class)
            ->assertTableActionHidden('disable_two_factor', $targetUser);
    }

    public function test_clear_session_action_creates_audit_trail(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $targetUser = User::factory()->create();

        Livewire::actingAs($superAdmin)
            ->test(UserSecurity::class)
            ->callTableAction('clear_session', $targetUser, data: [
                SensitiveActionPasswordConfirmation::FIELD => 'password',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('audit_trails', [
            'event_type' => 'security',
            'action' => 'two_factor_session_cleared',
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
        ]);
    }
}
