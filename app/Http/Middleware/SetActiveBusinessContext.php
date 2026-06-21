<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveBusinessContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedBusinessId = $request->integer('business_id');

        if ($requestedBusinessId > 0) {
            $isValid = Business::query()
                ->whereKey($requestedBusinessId)
                ->where('is_active', true)
                ->exists();

            if ($isValid) {
                session(['active_business_id' => $requestedBusinessId]);
            }
        }

        if (! session()->has('active_business_id')) {
            $fallbackBusinessId = Business::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');

            if ($fallbackBusinessId) {
                session(['active_business_id' => (int) $fallbackBusinessId]);
            }
        }

        return $next($request);
    }
}
