<?php

use App\Http\Middleware\AdminSecurityHeaders;
use App\Http\Middleware\RedirectAuthenticatedAdmin;
use App\Http\Middleware\RequireAdminAuthentication;
use App\Http\Middleware\RequireConfiguredEmailVerification;
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
        $middleware->validateCsrfTokens(except: []);
        $middleware->alias([
            'admin.auth' => RequireAdminAuthentication::class,
            'admin.guest' => RedirectAuthenticatedAdmin::class,
            'admin.headers' => AdminSecurityHeaders::class,
            'site.verified' => RequireConfiguredEmailVerification::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Central exception handling will be expanded as the CMS grows.
    })->create();
