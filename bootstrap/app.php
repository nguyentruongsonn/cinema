<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthenticateFromCookie;
use App\Http\Middleware\CookieToBearerToken;
use App\Http\Middleware\InternalMetricsAccess;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyPayOSWebhookSignature;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestIdMiddleware::class);

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
            'internal.metrics' => InternalMetricsAccess::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '/payment/payos/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $shouldRenderJson = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $apiError = function (Request $request, string $message, int $status, ?array $errors = null) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'request_id' => $request->attributes->get('request_id'),
            ], $status);
        };

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) use ($shouldRenderJson): bool {
            return $shouldRenderJson($request);
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($apiError, $shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return $apiError($request, 'Validation failed', 422, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($apiError, $shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return $apiError($request, 'Unauthenticated', 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($apiError, $shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return $apiError($request, 'Forbidden', 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($apiError, $shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return $apiError($request, 'Resource not found', 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($apiError, $shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return $apiError($request, 'Resource not found', 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($apiError, $shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return $apiError($request, 'Method not allowed', 405);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($apiError, $shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return $apiError($request, 'Too many requests', 429);
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($apiError, $shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            if ($e instanceof AuthorizationException) {
                return $apiError($request, 'Forbidden', 403);
            }

            if ($e instanceof HttpExceptionInterface) {
                return $apiError(
                    $request,
                    $e->getStatusCode() === 404 ? 'Resource not found' : 'Request failed',
                    $e->getStatusCode()
                );
            }

            return $apiError($request, 'Internal server error', 500);
        });
    })->create();
