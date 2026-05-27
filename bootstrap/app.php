<?php

use App\Http\Middleware\EnsureFactoryRole;
use App\Http\Middleware\EnsureFinanceRole;
use App\Http\Middleware\EnsureHrRole;
use App\Http\Middleware\EnsureSalesRole;
use App\Http\Middleware\EnsureWarehouseRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'factory' => EnsureFactoryRole::class,
            'finance' => EnsureFinanceRole::class,
            'hr' => EnsureHrRole::class,
            'sales' => EnsureSalesRole::class,
            'warehouse' => EnsureWarehouseRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
