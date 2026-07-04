<?php

use App\Http\Middleware\EnsureFactoryRole;
use App\Http\Middleware\EnsureFinanceRole;
use App\Http\Middleware\EnsureHrRole;
use App\Http\Middleware\EnsureProcurementRole;
use App\Http\Middleware\EnsureProjectRole;
use App\Http\Middleware\EnsureSalesRole;
use App\Http\Middleware\EnsureServiceRole;
use App\Http\Middleware\EnsureSuperAdminTwoFactorIsVerified;
use App\Http\Middleware\EnsureWarehouseRole;
use App\Http\Middleware\ProfileRoleEditRequests;
use App\Services\AuditTrailService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ProfileRoleEditRequests::class);

        $middleware->alias([
            'factory' => EnsureFactoryRole::class,
            'finance' => EnsureFinanceRole::class,
            'hr' => EnsureHrRole::class,
            'project' => EnsureProjectRole::class,
            'procurement' => EnsureProcurementRole::class,
            'sales' => EnsureSalesRole::class,
            'service' => EnsureServiceRole::class,
            'super_admin_2fa' => EnsureSuperAdminTwoFactorIsVerified::class,
            'warehouse' => EnsureWarehouseRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('admin/two-factor/*')) {
                return redirect()->guest('/admin/login');
            }

            return null;
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            $statusCode = $exception instanceof AuthorizationException
                ? 403
                : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : null);

            if ($statusCode !== 403 || ! $request->is('admin*', 'finance*', 'factory*', 'hr*', 'sales*', 'warehouse*', 'procurement*', 'project*', 'service*')) {
                return null;
            }

            app(AuditTrailService::class)->recordGeneric(
                eventType: 'security',
                action: 'restricted_url_attempt',
                userId: Auth::id(),
                description: 'Restricted Filament URL access attempt',
                metadata: [
                    'path' => $request->path(),
                    'method' => $request->method(),
                ],
            );

            return response('Not Found', 404);
        });
    })->create();
