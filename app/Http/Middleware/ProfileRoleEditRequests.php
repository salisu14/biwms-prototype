<?php

namespace App\Http\Middleware;

use App\Support\RoleEditProfiler;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProfileRoleEditRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! RoleEditProfiler::enabled() || ! $this->shouldProfile($request)) {
            return $next($request);
        }

        RoleEditProfiler::startRequest($request->path(), $request->method());

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            RoleEditProfiler::finishWithException($exception);

            throw $exception;
        }

        RoleEditProfiler::finish(
            statusCode: $response->getStatusCode(),
            responseBytes: $this->responseBytes($response),
        );

        return $response;
    }

    private function shouldProfile(Request $request): bool
    {
        return $request->is('admin/roles*')
            || $request->is('livewire/*')
            || $request->headers->has('X-Livewire');
    }

    private function responseBytes(Response $response): ?int
    {
        $contentLength = $response->headers->get('Content-Length');

        if (is_numeric($contentLength)) {
            return (int) $contentLength;
        }

        $content = $response->getContent();

        return is_string($content) ? strlen($content) : null;
    }
}
