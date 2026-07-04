<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\Auth\SuperAdminTwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SuperAdminTwoFactorSetupController extends Controller
{
    public function create(Request $request, SuperAdminTwoFactorService $twoFactorService): View
    {
        $user = $request->user();
        $secret = $request->session()->get('super_admin_2fa_setup_secret');

        if (! is_string($secret) || $secret === '') {
            $secret = $twoFactorService->generateSecret();
            $request->session()->put('super_admin_2fa_setup_secret', $secret);
        }

        abort_unless($user instanceof User, 404);

        return view('auth.two-factor.setup', [
            'action' => route('admin.two-factor.setup.store'),
            ...$this->setupViewData($twoFactorService, $user, $secret),
        ]);
    }

    public function store(
        Request $request,
        SuperAdminTwoFactorService $twoFactorService,
        AuditTrailService $auditTrailService
    ): View|Response {
        $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $secret = $request->session()->get('super_admin_2fa_setup_secret');

        abort_unless($user instanceof User && is_string($secret), 404);

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
                ...$this->setupViewData($twoFactorService, $user, $secret),
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

    /**
     * @return array{secret: string, issuer: string, otpauthUri: string, qrCodeSvg: string}
     */
    private function setupViewData(SuperAdminTwoFactorService $twoFactorService, User $user, string $secret): array
    {
        $otpauthUri = $twoFactorService->otpauthUri($user, $secret);

        return [
            'secret' => $secret,
            'issuer' => (string) config('app.name', 'BIWMS'),
            'otpauthUri' => $otpauthUri,
            'qrCodeSvg' => $twoFactorService->qrCodeSvg($otpauthUri),
        ];
    }
}
