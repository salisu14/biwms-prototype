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

        return $next($request);
    }
}
