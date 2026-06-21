<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use App\Services\Auth\SuperAdminTwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SuperAdminTwoFactorSetupController extends Controller
{
    public function create(Request $request, SuperAdminTwoFactorService $twoFactorService): View
    {
        $secret = $request->session()->get('super_admin_2fa_setup_secret');

        if (! is_string($secret) || $secret === '') {
            $secret = $twoFactorService->generateSecret();
            $request->session()->put('super_admin_2fa_setup_secret', $secret);
        }

        return view('auth.two-factor.setup', [
            'action' => route('admin.two-factor.setup.store'),
            'secret' => $secret,
        ]);
    }

    public function store(
        Request $request,
        SuperAdminTwoFactorService $twoFactorService,
        AuditTrailService $auditTrailService
    ): View|Response {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();
        $secret = $request->session()->get('super_admin_2fa_setup_secret');

        abort_unless($user && is_string($secret), 404);

        if (! $twoFactorService->verifyCode($secret, (string) $request->input('code'))) {
            $auditTrailService->recordGeneric(
                eventType: 'security',
                action: 'two_factor_setup_failed',
                auditable: $user,
                userId: $user->id,
                description: '2FA setup failed',
            );

            return response()->view('auth.two-factor.setup', [
                'action' => route('admin.two-factor.setup.store'),
                'secret' => $secret,
                'errorMessage' => 'The code was not valid. Try the current authenticator code.',
            ], 422);
        }

        $plainRecoveryCodes = $twoFactorService->enable($user, $secret, $user->id);

        $request->session()->forget('super_admin_2fa_setup_secret');
        $request->session()->put('two_factor_passed_at', now()->timestamp);
        $request->session()->put('super_admin_2fa_passed_at', now()->timestamp);

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_enabled',
            auditable: $user,
            userId: $user->id,
            description: '2FA enabled',
            metadata: ['recovery_code_count' => count($plainRecoveryCodes)],
        );

        return view('auth.two-factor.recovery-codes', [
            'codes' => $plainRecoveryCodes,
            'continueUrl' => $request->session()->pull('url.intended', '/admin'),
        ]);
    }
}
