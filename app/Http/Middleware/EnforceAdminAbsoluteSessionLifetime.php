<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminAbsoluteSessionLifetime
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $lifetimeMinutes = (int) config('security.admin_absolute_session_lifetime_minutes', 480);

        if ($lifetimeMinutes <= 0) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->timestamp;
        $authenticatedAt = (int) $session->get('admin_authenticated_at', $now);

        if (! $session->has('admin_authenticated_at')) {
            $session->put('admin_authenticated_at', $authenticatedAt);
        }

        if (($now - $authenticatedAt) >= ($lifetimeMinutes * 60)) {
            Auth::guard()->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->to(Filament::getLoginUrl() ?? '/admin/login')
                ->with('status', 'Your admin session lifetime expired. Please sign in again.');
        }

        return $next($request);
    }
}
