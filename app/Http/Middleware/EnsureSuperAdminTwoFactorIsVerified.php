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

        if (! $user?->hasRole('super_admin')) {
            return $next($request);
        }

        if ($request->routeIs('super-admin-2fa.*') || $request->is('admin/two-factor/*', 'super-admin/two-factor/*')) {
            return $next($request);
        }

        if (! $user->hasConfirmedTwoFactorAuthentication()) {
            app(AuditTrailService::class)->recordGeneric(
                eventType: 'security',
                action: 'super_admin_2fa_setup_required',
                auditable: $user,
                userId: $user->id,
                description: 'Super Admin login requires 2FA setup',
            );

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('super-admin-2fa.setup.create');
        }

        if (! $request->session()->has('super_admin_2fa_passed_at')) {
            app(AuditTrailService::class)->recordGeneric(
                eventType: 'security',
                action: 'super_admin_2fa_challenge_required',
                auditable: $user,
                userId: $user->id,
                description: 'Super Admin login requires 2FA challenge',
            );

            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('super-admin-2fa.challenge.create');
        }

        return $next($request);
    }
}
