<?php

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SuperAdminTwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        ->assertRedirect(route('super-admin-2fa.setup.create'));

    expect(route('super-admin-2fa.setup.create', absolute: false))->toBe('/admin/two-factor/setup');
    expect(AuditTrail::query()->where('action', 'super_admin_2fa_setup_required')->exists())->toBeTrue();
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

    $this->post(route('super-admin-2fa.setup.store'), ['code' => $code])
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
        ->assertRedirect(route('super-admin-2fa.challenge.create'));

    $this->post(route('super-admin-2fa.challenge.store'), ['code' => $service->currentCode($secret)])
        ->assertRedirect('/admin/roles');

    expect(session()->has('super_admin_2fa_passed_at'))->toBeTrue()
        ->and(AuditTrail::query()->where('action', 'super_admin_2fa_challenge_passed')->exists())->toBeTrue();
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
        ->post(route('super-admin-2fa.challenge.store'), ['code' => 'ABCDE-FGHIJ-KLMNO'])
        ->assertRedirect('/admin');

    expect($superAdmin->fresh()->two_factor_recovery_codes)->toHaveCount(0)
        ->and(AuditTrail::query()->pluck('metadata')->flatten()->join(' '))->not->toContain('ABCDE-FGHIJ-KLMNO');
});
