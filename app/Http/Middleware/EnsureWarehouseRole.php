<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWarehouseRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole([
            'warehouse-worker',
            'warehouse-manager',
            'admin',
            'super_admin',
        ])) {
            abort(403);
        }

        return $next($request);
    }
}
