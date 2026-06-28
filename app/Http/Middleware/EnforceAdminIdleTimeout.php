<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminIdleTimeout
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

        $timeoutMinutes = (int) config('security.admin_idle_timeout_minutes', 30);

        if ($timeoutMinutes <= 0) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->timestamp;
        $lastActivityAt = (int) $session->get('admin_last_activity_at', $now);

        if (($now - $lastActivityAt) >= ($timeoutMinutes * 60)) {
            Auth::guard()->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->to(Filament::getLoginUrl() ?? '/admin/login')
                ->with('status', 'Your admin session expired due to inactivity.');
        }

        $session->put('admin_last_activity_at', $now);

        return $next($request);
    }
}
