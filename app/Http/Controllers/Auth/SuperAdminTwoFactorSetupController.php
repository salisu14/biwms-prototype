<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use App\Services\Auth\SuperAdminTwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SuperAdminTwoFactorSetupController extends Controller
{
    public function create(Request $request, SuperAdminTwoFactorService $twoFactorService): Response
    {
        $secret = $request->session()->get('super_admin_2fa_setup_secret');

        if (! is_string($secret) || $secret === '') {
            $secret = $twoFactorService->generateSecret();
            $request->session()->put('super_admin_2fa_setup_secret', $secret);
        }

        return response($this->formHtml(
            title: 'Set up Super Admin 2FA',
            action: route('super-admin-2fa.setup.store'),
            body: 'Add this TOTP secret to your authenticator app, then enter the current code.',
            secret: $secret,
        ));
    }

    public function store(
        Request $request,
        SuperAdminTwoFactorService $twoFactorService,
        AuditTrailService $auditTrailService
    ): Response {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();
        $secret = $request->session()->get('super_admin_2fa_setup_secret');

        abort_unless($user && $user->hasRole('super_admin') && is_string($secret), 404);

        if (! $twoFactorService->verifyCode($secret, (string) $request->input('code'))) {
            $auditTrailService->recordGeneric(
                eventType: 'security',
                action: 'super_admin_2fa_setup_failed',
                auditable: $user,
                userId: $user->id,
                description: 'Super Admin 2FA setup failed',
            );

            return response($this->formHtml(
                title: 'Set up Super Admin 2FA',
                action: route('super-admin-2fa.setup.store'),
                body: 'The code was not valid. Try the current authenticator code.',
                secret: $secret,
            ), 422);
        }

        $plainRecoveryCodes = $twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $twoFactorService->hashRecoveryCodes($plainRecoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('super_admin_2fa_setup_secret');
        $request->session()->put('super_admin_2fa_passed_at', now()->timestamp);

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_enabled',
            auditable: $user,
            userId: $user->id,
            description: 'Super Admin 2FA enabled',
            metadata: ['recovery_code_count' => count($plainRecoveryCodes)],
        );

        return response($this->recoveryCodesHtml($plainRecoveryCodes));
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function recoveryCodesHtml(array $codes): string
    {
        $items = collect($codes)
            ->map(fn (string $code): string => '<li><code>'.e($code).'</code></li>')
            ->implode('');

        return <<<HTML
            <!doctype html>
            <title>Recovery Codes</title>
            <h1>Recovery Codes</h1>
            <p>Store these recovery codes securely. They will not be shown again.</p>
            <ol>{$items}</ol>
            <a href="/admin">Continue</a>
            HTML;
    }

    private function formHtml(string $title, string $action, string $body, string $secret): string
    {
        $csrf = csrf_field();

        return <<<HTML
            <!doctype html>
            <title>{$title}</title>
            <h1>{$title}</h1>
            <p>{$body}</p>
            <p><strong>TOTP Secret:</strong> <code>{$secret}</code></p>
            <form method="POST" action="{$action}">
                {$csrf}
                <label>Authenticator code <input name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
                <button type="submit">Verify</button>
            </form>
            HTML;
    }
}
