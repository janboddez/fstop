<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: '', // We don't necessarily want to prefix all API routes.
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Don't set cookies on public routes.
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            // \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            // \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            // \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
        ]);

        $middleware->prependToGroup('api', [
            // Require a JSON response.
            \App\Http\Middleware\EnsureJsonResponse::class,
            // Tell browsers not to cache. Might not be very relevant here.
            \Illuminate\Http\Middleware\SetCacheHeaders::class . ':private;max_age=0;must_revalidate',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('activitypub/*')) {
                return response()->json([
                     // At least Mastodon seems to return an `error` property, rather than Laravel's default `message`.
                    'error' => $e->getMessage(),
                ], 404);
            }
        });
    })
    ->create();
