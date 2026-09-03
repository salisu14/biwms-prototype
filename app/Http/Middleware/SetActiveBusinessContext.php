<?php

namespace App\Http\Middleware;

use App\Services\Business\BusinessContextService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveBusinessContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedBusinessId = $request->integer('business_id');

        if ($requestedBusinessId > 0) {
            app(BusinessContextService::class)->setActive($requestedBusinessId);
        } elseif (filled(session('active_business_id'))) {
            try {
                app(BusinessContextService::class)->resolve();
            } catch (AuthorizationException) {
                session()->forget('active_business_id');
                abort(403, 'Your active business access is no longer valid.');
            }
        }

        return $next($request);
    }
}
