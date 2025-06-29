<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append : [
            App\Http\Middleware\SecurityHeaders::class,
            App\Http\Middleware\AdvancedRateLimiting::class,
            App\Http\Middleware\Localization::class,
            App\Http\Middleware\SecurityMonitoring::class,

            // App\Http\Middleware\LogAllRequests::class,
        ]);

        $middleware->alias([
            'admin.context' => App\Http\Middleware\AdminContextMiddleware::class,
            'permission' => App\Http\Middleware\HasPermission::class,
            'developer' => App\Http\Middleware\IsDeveloper::class,
            'security' => App\Http\Middleware\SecurityMonitoring::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
