<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Global security headers (applied to all responses).
        $middleware->append(\XetaSuite\Http\Middleware\SecurityHeaders::class);

        // Register middleware aliases
        $middleware->alias([
            'recaptcha' => \XetaSuite\Http\Middleware\VerifyRecaptcha::class,
        ]);

        // Middlewares for web routes
        $middleware->web(append: [
            \XetaSuite\Http\Middleware\SetLocale::class,
            \XetaSuite\Http\Middleware\SetCurrentSite::class,
            \XetaSuite\Http\Middleware\SetCurrentPermissionsAndRoles::class,
        ]);

        // Middlewares for API routes (stateful SPA via Sanctum)
        $middleware->api(append: [
            \XetaSuite\Http\Middleware\SetLocale::class,
            \XetaSuite\Http\Middleware\SetCurrentSite::class,
            \XetaSuite\Http\Middleware\SetCurrentPermissionsAndRoles::class,
            \XetaSuite\Http\Middleware\DemoModeRestriction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\XetaSuite\Exceptions\Ai\LlmRateLimitException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Trop de demandes. Veuillez patienter quelques instants avant de réessayer.',
                ], 429);
            }
        });
    })->create();
