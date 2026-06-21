<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditTrailService;
use App\Services\Auth\SuperAdminTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SuperAdminTwoFactorChallengeController extends Controller
{
    public function create(): Response
    {
        return response($this->formHtml('Super Admin 2FA Challenge', route('super-admin-2fa.challenge.store'), 'Enter an authenticator code or a recovery code.'));
    }

    public function store(
        Request $request,
        SuperAdminTwoFactorService $twoFactorService,
        AuditTrailService $auditTrailService
    ): Response|RedirectResponse {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        abort_unless($user && $user->hasRole('super_admin') && $user->hasConfirmedTwoFactorAuthentication(), 404);

        $code = (string) $request->input('code');
        $verified = $twoFactorService->verifyCode((string) $user->two_factor_secret, $code)
            || $twoFactorService->consumeRecoveryCode($user, $code);

        if (! $verified) {
            $auditTrailService->recordGeneric(
                eventType: 'security',
                action: 'super_admin_2fa_challenge_failed',
                auditable: $user,
                userId: $user->id,
                description: 'Super Admin 2FA challenge failed',
            );

            return response($this->formHtml('Super Admin 2FA Challenge', route('super-admin-2fa.challenge.store'), 'The code was not valid.'), 422);
        }

        $request->session()->put('super_admin_2fa_passed_at', now()->timestamp);

        $auditTrailService->recordGeneric(
            eventType: 'security',
            action: 'super_admin_2fa_challenge_passed',
            auditable: $user,
            userId: $user->id,
            description: 'Super Admin 2FA challenge completed',
        );

        return redirect('/admin');
    }

    private function formHtml(string $title, string $action, string $body): string
    {
        $csrf = csrf_field();

        return <<<HTML
            <!doctype html>
            <title>{$title}</title>
            <h1>{$title}</h1>
            <p>{$body}</p>
            <form method="POST" action="{$action}">
                {$csrf}
                <label>Code <input name="code" autocomplete="one-time-code" required></label>
                <button type="submit">Verify</button>
            </form>
            HTML;
    }
}
