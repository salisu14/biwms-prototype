<?php

namespace App\Http\Middleware;

use App\Services\AuditTrailService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminTwoFactorIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->requiresTwoFactor()) {
            return $next($request);
        }

        if (
            $request->routeIs('admin.two-factor.setup.*', 'admin.two-factor.challenge.*')
            || $request->is('admin/two-factor/setup', 'admin/two-factor/challenge', 'super-admin/two-factor/*', 'logout', 'forgot-password', 'reset-password*')
        ) {
            return $next($request);
        }

        if (! $user->hasConfirmedTwoFactorAuthentication()) {
            app(AuditTrailService::class)->recordGeneric(
                eventType: 'security',
                action: 'two_factor_setup_required',
                auditable: $user,
                userId: $user->id,
                description: 'User login requires 2FA setup',
            );

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('admin.two-factor.setup.create');
        }

        if (! $request->session()->has('two_factor_passed_at')) {
            app(AuditTrailService::class)->recordGeneric(
                eventType: 'security',
                action: 'two_factor_challenge_required',
                auditable: $user,
                userId: $user->id,
                description: 'User login requires 2FA challenge',
            );

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('admin.two-factor.challenge.create');
        }

        return $next($request);
    }
}
