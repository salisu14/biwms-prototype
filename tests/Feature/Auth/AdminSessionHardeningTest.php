<?php

use App\Http\Middleware\EnforceAdminAbsoluteSessionLifetime;
use App\Http\Middleware\EnforceAdminIdleTimeout;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs out admin sessions after configured inactivity', function (): void {
    config()->set('security.admin_idle_timeout_minutes', 30);
    config()->set('security.admin_absolute_session_lifetime_minutes', 480);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'admin_authenticated_at' => now()->subMinutes(45)->timestamp,
            'admin_last_activity_at' => now()->subMinutes(31)->timestamp,
        ])
        ->get('/admin')
        ->assertRedirect('/admin/login');

    $this->assertGuest();
});

it('keeps active admin sessions valid before idle timeout', function (): void {
    config()->set('security.admin_idle_timeout_minutes', 30);
    config()->set('security.admin_absolute_session_lifetime_minutes', 480);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'admin_authenticated_at' => now()->subMinutes(45)->timestamp,
            'admin_last_activity_at' => now()->subMinutes(10)->timestamp,
        ])
        ->get('/admin')
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('logs out admin sessions after the absolute lifetime', function (): void {
    config()->set('security.admin_idle_timeout_minutes', 30);
    config()->set('security.admin_absolute_session_lifetime_minutes', 480);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'admin_authenticated_at' => now()->subMinutes(481)->timestamp,
            'admin_last_activity_at' => now()->subMinutes(1)->timestamp,
        ])
        ->get('/admin')
        ->assertRedirect('/admin/login');

    $this->assertGuest();
});

it('documents secure session cookie and password-change session protections', function (): void {
    config()->set('session.secure', true);
    config()->set('session.http_only', true);
    config()->set('session.same_site', 'lax');

    $adminPanelProvider = new AdminPanelProvider(app());
    $panel = $adminPanelProvider->panel(Panel::make());

    expect(config('session.secure'))->toBeTrue()
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and($panel->getMiddleware())->toContain(
            EnforceAdminAbsoluteSessionLifetime::class,
            EnforceAdminIdleTimeout::class,
            AuthenticateSession::class,
        );
});
