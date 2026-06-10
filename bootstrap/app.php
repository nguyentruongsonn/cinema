<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthenticateFromCookie;
use App\Http\Middleware\CookieToBearerToken;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyPayOSWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply security headers to all responses
        $middleware->append(SecurityHeaders::class);

        // JWT auth cookies are issued by API routes as plain HttpOnly cookies.
        // Do not let Laravel's web EncryptCookies middleware decrypt/encrypt them;
        // otherwise SSR web requests cannot read access_token and render as guest.
        $middleware->encryptCookies(except: [
            'access_token',
            'refresh_token',
        ]);

        // API frontend uses HttpOnly JWT cookies. Convert the access_token cookie
        // into a Bearer token before Laravel's auth:api middleware runs.
        $middleware->api(prepend: [
            CookieToBearerToken::class,
        ]);

        // Web routes: Authenticate users from JWT cookie for server-side rendering
        // This makes Auth::check(), Auth::user(), @auth, @guest work in Blade views
        $middleware->web(append: [
            AuthenticateFromCookie::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'verify.payos' => VerifyPayOSWebhookSignature::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/payment/payos/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
