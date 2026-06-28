<?php

use App\Filament\Pages\UserSecurity;
use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SuperAdminTwoFactorService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
});

it('requires Super Admin to set up two factor authentication before panel access', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $this->actingAs($superAdmin)
        ->get('/admin')
        ->assertRedirect(route('admin.two-factor.setup.create'));

    expect(route('admin.two-factor.setup.create', absolute: false))->toBe('/admin/two-factor/setup');
    expect(AuditTrail::query()->where('action', 'two_factor_setup_required')->exists())->toBeTrue();
});

it('enables Super Admin TOTP with hashed recovery codes and audits the event', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $service = app(SuperAdminTwoFactorService::class);

    $this->actingAs($superAdmin)
        ->get('/admin/two-factor/setup')
        ->assertSuccessful()
        ->assertSee('TOTP Secret');

    $secret = session('super_admin_2fa_setup_secret');
    $code = $service->currentCode($secret);

    $this->post(route('admin.two-factor.setup.store'), ['code' => $code])
        ->assertSuccessful()
        ->assertSee('Recovery Codes');

    $superAdmin->refresh();

    expect($superAdmin->hasConfirmedTwoFactorAuthentication())->toBeTrue()
        ->and($superAdmin->two_factor_recovery_codes)->toHaveCount(8)
        ->and(Hash::needsRehash($superAdmin->two_factor_recovery_codes[0]))->toBeFalse()
        ->and(AuditTrail::query()->where('action', 'two_factor_enabled')->exists())->toBeTrue();
});

it('requires and accepts a successful Super Admin two factor challenge', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $service = app(SuperAdminTwoFactorService::class);
    $secret = $service->generateSecret();

    $superAdmin->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->hashRecoveryCodes(['ABCDE-FGHIJ-KLMNO']),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($superAdmin)
        ->get('/admin/roles')
        ->assertRedirect(route('admin.two-factor.challenge.create'));

    $this->post(route('admin.two-factor.challenge.store'), ['code' => $service->currentCode($secret)])
        ->assertRedirect('/admin/roles');

    expect(session()->has('two_factor_passed_at'))->toBeTrue()
        ->and(AuditTrail::query()->where('action', 'two_factor_challenge_passed')->exists())->toBeTrue();
});

it('redirects legacy Super Admin 2FA URLs to the active admin panel paths', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $this->actingAs($superAdmin)
        ->get('/super-admin/two-factor/setup')
        ->assertRedirect('/admin/two-factor/setup');

    $this->actingAs($superAdmin)
        ->get('/super-admin/two-factor/challenge')
        ->assertRedirect('/admin/two-factor/challenge');
});

it('accepts recovery codes once without logging plaintext values', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $service = app(SuperAdminTwoFactorService::class);

    $superAdmin->forceFill([
        'two_factor_secret' => $service->generateSecret(),
        'two_factor_recovery_codes' => $service->hashRecoveryCodes(['ABCDE-FGHIJ-KLMNO']),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($superAdmin)
        ->post(route('admin.two-factor.challenge.store'), ['code' => 'ABCDE-FGHIJ-KLMNO'])
        ->assertRedirect('/admin');

    expect($superAdmin->fresh()->two_factor_recovery_codes)->toHaveCount(0)
        ->and($superAdmin->fresh()->twoFactorRecoveryCodesRemaining())->toBe(0)
        ->and(AuditTrail::query()->where('action', 'two_factor_recovery_code_used')->exists())->toBeTrue()
        ->and(AuditTrail::query()->pluck('metadata')->flatten()->join(' '))->not->toContain('ABCDE-FGHIJ-KLMNO');
});

it('lets users view and manage their own 2FA lifecycle', function (): void {
    $user = User::factory()->create();
    $service = app(SuperAdminTwoFactorService::class);
    $secret = $service->generateSecret();

    $this->actingAs($user)
        ->get(route('admin.two-factor.manage'))
        ->assertSuccessful()
        ->assertSee('Disabled');

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->hashRecoveryCodes(['ABCDE-FGHIJ-KLMNO']),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->post(route('admin.two-factor.recovery-codes.regenerate'), [
            'password' => 'password',
        ])
        ->assertSuccessful()
        ->assertSee('Recovery Codes');

    expect($user->fresh()->twoFactorRecoveryCodesRemaining())->toBe(8)
        ->and(AuditTrail::query()->where('action', 'two_factor_recovery_codes_regenerated')->exists())->toBeTrue();

    $this->post(route('admin.two-factor.disable'), [
        'confirmation' => $user->email,
        'password' => 'password',
    ])
        ->assertSuccessful()
        ->assertSee('2FA has been disabled');

    expect($user->fresh()->hasConfirmedTwoFactorAuthentication())->toBeFalse()
        ->and(AuditTrail::query()->where('action', 'two_factor_disabled')->exists())->toBeTrue();
});

it('rejects direct backend 2FA management requests without current password confirmation', function (): void {
    $user = User::factory()->create();
    $service = app(SuperAdminTwoFactorService::class);

    $user->forceFill([
        'two_factor_secret' => $service->generateSecret(),
        'two_factor_recovery_codes' => $service->hashRecoveryCodes(['ABCDE-FGHIJ-KLMNO']),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->from(route('admin.two-factor.manage'))
        ->post(route('admin.two-factor.recovery-codes.regenerate'))
        ->assertRedirect(route('admin.two-factor.manage'))
        ->assertSessionHasErrors('password');

    expect($user->fresh()->twoFactorRecoveryCodesRemaining())->toBe(1)
        ->and(AuditTrail::query()->where('action', 'two_factor_recovery_codes_regenerated')->exists())->toBeFalse();
});

it('requires admin role users and explicitly flagged users to complete 2FA', function (): void {
    Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $ordinaryUser = User::factory()->create();
    $flaggedUser = User::factory()->create(['two_factor_required' => true]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect(route('admin.two-factor.setup.create'));

    $this->actingAs($ordinaryUser)
        ->get('/admin/two-factor/manage')
        ->assertSuccessful();

    $this->actingAs($flaggedUser)
        ->get('/admin')
        ->assertRedirect(route('admin.two-factor.setup.create'));
});

it('excludes setup and challenge routes from 2FA redirect loops', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $service = app(SuperAdminTwoFactorService::class);

    $this->actingAs($superAdmin)
        ->get(route('admin.two-factor.setup.create'))
        ->assertSuccessful();

    $superAdmin->forceFill([
        'two_factor_secret' => $service->generateSecret(),
        'two_factor_recovery_codes' => $service->hashRecoveryCodes(['ABCDE-FGHIJ-KLMNO']),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($superAdmin)
        ->get(route('admin.two-factor.challenge.create'))
        ->assertSuccessful();
});

it('lets Super Admin manage user security and audits sensitive actions', function (): void {
    $superAdmin = User::factory()->create();
    $target = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $service = app(SuperAdminTwoFactorService::class);

    $superAdmin->forceFill([
        'two_factor_secret' => $service->generateSecret(),
        'two_factor_recovery_codes' => $service->hashRecoveryCodes(['ABCDE-FGHIJ-KLMNO']),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($target)
        ->get(UserSecurity::getUrl())
        ->assertNotFound();

    $this->actingAs($superAdmin)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get(UserSecurity::getUrl())
        ->assertSuccessful()
        ->assertSee('User Security');

    Livewire::actingAs($superAdmin)
        ->test(UserSecurity::class)
        ->callTableAction('require_two_factor', $target, data: [
            SensitiveActionPasswordConfirmation::FIELD => 'password',
        ])
        ->callTableAction('force_reset', $target, data: [
            SensitiveActionPasswordConfirmation::FIELD => 'password',
        ])
        ->assertHasNoTableActionErrors();

    expect($target->fresh()->two_factor_required)->toBeTrue()
        ->and($target->fresh()->two_factor_reset_at)->not->toBeNull()
        ->and(AuditTrail::query()->where('action', 'two_factor_required')->exists())->toBeTrue()
        ->and(AuditTrail::query()->where('action', 'two_factor_admin_reset')->exists())->toBeTrue();
});

it('prevents recovery code reuse', function (): void {
    $user = User::factory()->create();
    $service = app(SuperAdminTwoFactorService::class);

    $user->forceFill([
        'two_factor_secret' => $service->generateSecret(),
        'two_factor_recovery_codes' => $service->hashRecoveryCodes(['ABCDE-FGHIJ-KLMNO']),
        'two_factor_confirmed_at' => now(),
    ])->save();

    expect($service->consumeRecoveryCode($user->fresh(), 'ABCDE-FGHIJ-KLMNO'))->toBeTrue()
        ->and($service->consumeRecoveryCode($user->fresh(), 'ABCDE-FGHIJ-KLMNO'))->toBeFalse()
        ->and($user->fresh()->twoFactorRecoveryCodesRemaining())->toBe(0);
});
