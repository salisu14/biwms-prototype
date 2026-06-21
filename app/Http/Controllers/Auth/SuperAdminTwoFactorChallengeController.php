<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use App\Services\Auth\SuperAdminTwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SuperAdminTwoFactorChallengeController extends Controller
{
    public function create(): View
    {
        return view('auth.two-factor.challenge', [
            'action' => route('admin.two-factor.challenge.store'),
        ]);
    }

    public function store(
        Request $request,
        SuperAdminTwoFactorService $twoFactorService,
        AuditTrailService $auditTrailService
    ): Response|RedirectResponse {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        abort_unless($user && $user->hasConfirmedTwoFactorAuthentication(), 404);

        $code = (string) $request->input('code');
        $verified = $twoFactorService->verifyCode((string) $user->two_factor_secret, $code)
            || $twoFactorService->consumeRecoveryCode($user, $code);

        if (! $verified) {
            $auditTrailService->recordGeneric(
                eventType: 'security',
                action: 'two_factor_challenge_failed',
                auditable: $user,
                userId: $user->id,
                description: '2FA challenge failed',
            );

            return response()->view('auth.two-factor.challenge', [
                'action' => route('admin.two-factor.challenge.store'),
                'errorMessage' => 'The code was not valid.',
            ], 422);
        }

        $user->forceFill(['two_factor_last_challenged_at' => now()])->save();
        $request->session()->put('two_factor_passed_at', now()->timestamp);
        $request->session()->put('super_admin_2fa_passed_at', now()->timestamp);

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'two_factor_challenge_passed',
            auditable: $user,
            userId: $user->id,
            description: '2FA challenge completed',
        );

        return redirect()->intended('/admin');
    }
}
