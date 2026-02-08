<?php

use App\Http\Middleware\CheckAccountStatus;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register account status check middleware for web routes
        $middleware->web(append: [
            CheckAccountStatus::class,
        ]);

        // Alias for manual usage
        $middleware->alias([
            'account.status' => CheckAccountStatus::class,
        ]);

        // ==================== MID-MASTER SECURITY ====================
        // Rate limiting for authentication routes (5 attempts per minute)
        $middleware->throttleApi();
        $middleware->throttleWithRedis(); // If Redis available
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
