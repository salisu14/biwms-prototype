<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use App\Services\Auth\SuperAdminTwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwoFactorManagementController extends Controller
{
    public function index(Request $request): View
    {
        return view('auth.two-factor.manage', [
            'user' => $request->user()->fresh('roles'),
        ]);
    }

    public function disable(Request $request, SuperAdminTwoFactorService $twoFactorService, AuditTrailService $auditTrailService): Response
    {
        $user = $request->user();

        abort_unless($user, 404);

        if ($user->requiresTwoFactor()) {
            return response()->view('auth.two-factor.manage', [
                'user' => $user->fresh('roles'),
                'errorMessage' => '2FA is required for your role or account and cannot be disabled here.',
            ], 422);
        }

        $request->validate(['confirmation' => ['required', 'string']]);

        if ((string) $request->input('confirmation') !== $user->email) {
            return response()->view('auth.two-factor.manage', [
                'user' => $user->fresh('roles'),
                'errorMessage' => 'Type your email address to confirm disabling 2FA.',
            ], 422);
        }

        $this->validateCurrentPassword($request);

        $twoFactorService->disable($user, $user->id);
        $request->session()->forget(['two_factor_passed_at', 'super_admin_2fa_passed_at']);

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_disabled',
            auditable: $user,
            userId: $user->id,
            description: 'User disabled 2FA',
        );

        return response()->view('auth.two-factor.manage', [
            'user' => $user->fresh('roles'),
            'statusMessage' => '2FA has been disabled.',
        ]);
    }

    public function resetAuthenticator(Request $request, SuperAdminTwoFactorService $twoFactorService, AuditTrailService $auditTrailService): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 404);

        $this->validateCurrentPassword($request);

        $twoFactorService->forceReset($user, $user->id);
        $request->session()->forget(['super_admin_2fa_setup_secret', 'two_factor_passed_at', 'super_admin_2fa_passed_at']);

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_reset',
            auditable: $user,
            userId: $user->id,
            description: 'User reset authenticator app enrollment',
        );

        return redirect()->route('admin.two-factor.setup.create');
    }

    public function regenerateRecoveryCodes(Request $request, SuperAdminTwoFactorService $twoFactorService, AuditTrailService $auditTrailService): View
    {
        $user = $request->user();

        abort_unless($user && $user->hasConfirmedTwoFactorAuthentication(), 404);

        $this->validateCurrentPassword($request);

        $plainRecoveryCodes = $twoFactorService->regenerateRecoveryCodes($user);

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_recovery_codes_regenerated',
            auditable: $user,
            userId: $user->id,
            description: 'User regenerated 2FA recovery codes',
            metadata: ['recovery_code_count' => count($plainRecoveryCodes)],
        );

        return view('auth.two-factor.recovery-codes', [
            'codes' => $plainRecoveryCodes,
            'continueUrl' => route('admin.two-factor.manage'),
        ]);
    }

    private function validateCurrentPassword(Request $request): void
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);
    }
}
