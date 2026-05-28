<?php

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
        // CORS harus paling depan agar header dikirim sebelum response lain
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        $middleware->append(\App\Http\Middleware\AllowIframeMiddleware::class);

        $middleware->validateCsrfTokens(except: [
            'mcp',
            'api/sso/generate-token',
            'login', // Dikecewakan dari CSRF untuk mendukung testing login di iframe HTTP
            // Route chatbot dikecualikan dari CSRF karena pakai Bearer token saat di iframe
            // Saat akses langsung (session), CSRF tetap dikirim dari meta tag
            'chatbot/*',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocaleMiddleware::class,
        ], replace: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\CustomValidateCsrfToken::class,
        ]);

        $middleware->alias([
            'admin'            => \App\Http\Middleware\AdminMiddleware::class,
            'auth.smart'       => \App\Http\Middleware\SanctumOrSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
