<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dashboard memanggil /api dari domain yang sama memakai session + cookie (Sanctum SPA auth).
        $middleware->statefulApi();

        // Webhook provider donasi datang dari server luar tanpa CSRF token; keamanannya lewat signature HMAC.
        $middleware->validateCsrfTokens(except: ['api/webhooks/*']);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/live-session');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua error di /api dibalas JSON (401/403/404/422), bukan halaman HTML atau redirect.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
