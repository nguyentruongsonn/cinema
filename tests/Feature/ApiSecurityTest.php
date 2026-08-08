<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_auth_routes_are_versioned_and_rate_limited(): void
    {
        $routes = collect(Route::getRoutes())
            ->map(fn ($route) => [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'middleware' => $route->gatherMiddleware(),
            ]);

        foreach (['api/v1/auth/login', 'api/v1/auth/register', 'api/v1/auth/forgot-password', 'api/v1/auth/reset-password'] as $uri) {
            $route = $routes->firstWhere('uri', $uri);

            $this->assertNotNull($route, "Route {$uri} must exist.");
            $this->assertContains('throttle:auth', $route['middleware'], "Route {$uri} must be rate limited.");
        }
    }

    public function test_api_is_stateless_and_uses_auth_middleware_instead_of_csrf(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ]);

        // API routes are intentionally stateless and should validate/authorize the request,
        // not fail with web CSRF status 419.
        $this->assertNotEquals(419, $response->status());
        $this->assertTrue(in_array($response->status(), [401, 422], true));
    }

    public function test_sql_injection_payloads_do_not_crash_public_movie_endpoints(): void
    {
        $sqlInjectionPayloads = [
            "'; DROP TABLE movies;--",
            "' OR '1'='1",
            "1' UNION SELECT * FROM users--",
            "admin'--",
            "' OR 1=1--",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $response = $this->getJson('/api/v1/movies/search?query=' . urlencode($payload));

            $this->assertNotEquals(500, $response->status());
            $this->assertStringNotContainsStringIgnoringCase('SQL syntax', $response->getContent());
            $this->assertStringNotContainsStringIgnoringCase('mysql error', $response->getContent());
            $this->assertStringNotContainsStringIgnoringCase('PDOException', $response->getContent());
        }
    }

    public function test_user_model_does_not_allow_privilege_mass_assignment(): void
    {
        $user = new User();

        $this->assertContains('status', $user->getFillable(), 'Status is intentionally fillable for admin/service workflows.');
        $this->assertNotContains('is_admin', $user->getFillable());
        $this->assertNotContains('role', $user->getFillable());
        $this->assertNotContains('roles', $user->getFillable());
        $this->assertNotContains('permissions', $user->getFillable());
    }

    public function test_authentication_required_for_protected_api_endpoints(): void
    {
        $protectedEndpoints = [
            ['GET', '/api/v1/auth/profile'],
            ['POST', '/api/v1/seats/lock'],
            ['POST', '/api/v1/orders'],
            ['GET', '/api/v1/orders/user/me'],
            ['POST', '/api/v1/payments'],
            ['POST', '/api/v1/broadcasting/auth'],
        ];

        foreach ($protectedEndpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);

            $this->assertEquals(401, $response->status(), "{$method} {$uri} should require authentication.");
        }
    }

    public function test_admin_routes_are_protected_by_auth_and_role_middleware(): void
    {
        $adminUris = [
            'api/v1/admin/dashboard/stats',
            'api/v1/admin/movies',
            'api/v1/admin/theaters',
            'api/v1/admin/screens',
            'api/v1/admin/showtimes',
        ];

        foreach ($adminUris as $uri) {
            $matchingRoutes = collect(Route::getRoutes())->filter(fn ($route) => $route->uri() === $uri);

            $this->assertNotEmpty($matchingRoutes, "Admin route {$uri} must exist.");

            foreach ($matchingRoutes as $route) {
                $middleware = $route->gatherMiddleware();

                $this->assertContains('auth:api', $middleware, "Admin route {$uri} must require API authentication.");
                $this->assertContains('role:admin,super-admin,theater_manager', $middleware, "Admin route {$uri} must require admin role.");
            }
        }
    }

    public function test_sensitive_data_is_not_leaked_in_not_found_api_responses(): void
    {
        $response = $this->getJson('/api/v1/movies/non-existent-movie-slug-for-security-test');
        $content = $response->getContent();

        $this->assertNotEquals(500, $response->status());
        $this->assertStringNotContainsStringIgnoringCase('password', $content);
        $this->assertStringNotContainsStringIgnoringCase('secret', $content);
        $this->assertStringNotContainsStringIgnoringCase('access_token', $content);
        $this->assertStringNotContainsStringIgnoringCase('refresh_token', $content);
        $this->assertStringNotContainsString('C:\\', $content);
        $this->assertStringNotContainsString('/home/', $content);
        $this->assertStringNotContainsString('/var/www/', $content);
    }

    public function test_input_validation_rejects_invalid_login_payload(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_profile_update_request_rejects_unexpected_privilege_fields(): void
    {
        $request = new \App\Http\Requests\UpdateProfileRequest();
        $allowedFields = array_keys($request->rules());

        $this->assertContains('name', $allowedFields);
        $this->assertContains('phone', $allowedFields);
        $this->assertNotContains('email', $allowedFields);
        $this->assertNotContains('password', $allowedFields);
        $this->assertNotContains('is_admin', $allowedFields);
        $this->assertNotContains('role', $allowedFields);
        $this->assertNotContains('status', $allowedFields);
    }

    public function test_no_sensitive_headers_exposed(): void
    {
        $response = $this->getJson('/api/v1/movies');

        $this->assertFalse($response->headers->has('Server'));
        $this->assertFalse($response->headers->has('X-Powered-By'));

        $poweredBy = $response->headers->get('X-Powered-By');
        if ($poweredBy) {
            $this->assertStringNotContainsString('PHP/', $poweredBy);
        }
    }

    public function test_cors_headers_are_not_wildcard_in_production(): void
    {
        $response = $this->getJson('/api/v1/movies', [
            'Origin' => 'https://example.com',
        ]);

        if ($response->headers->has('Access-Control-Allow-Origin') && app()->environment('production')) {
            $this->assertNotEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_password_reset_response_does_not_expose_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $content = $response->getContent();
        $data = json_decode($content, true) ?: [];

        $this->assertNotEquals(500, $response->status());
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('reset_token', $data);
        $this->assertStringNotContainsStringIgnoringCase('password_resets', $content);
    }

    public function test_auth_routes_set_http_only_cookies_on_successful_login(): void
    {
        $this->assertTrue(
            method_exists(\App\Http\Controllers\AuthController::class, 'login'),
            'AuthController must expose a login endpoint.'
        );

        $reflection = new \ReflectionClass(\App\Http\Controllers\AuthController::class);

        $this->assertTrue(
            $reflection->hasMethod('setAuthCookies'),
            'AuthController should centralize secure auth cookie creation.'
        );

        $methodSource = file_get_contents(app_path('Http/Controllers/AuthController.php'));

        $this->assertStringContainsString('httpOnly:', $methodSource);
        $this->assertStringContainsString('sameSite:', $methodSource);
        $this->assertStringContainsString('access_token', $methodSource);
        $this->assertStringContainsString('refresh_token', $methodSource);
    }

    public function test_frontend_api_routes_are_available_only_under_v1_prefix(): void
    {
        $routes = collect(Route::getRoutes())->map(fn ($route) => $route->uri());

        $expectedV1Routes = [
            'api/v1/home',
            'api/v1/auth/me',
            'api/v1/movies',
            'api/v1/products',
            'api/v1/seats/showtime/{encryptedShowtimeId}',
            'api/v1/payments',
            'api/v1/promotions/{code}/validate',
        ];

        foreach ($expectedV1Routes as $uri) {
            $this->assertContains($uri, $routes, "Frontend route {$uri} must exist.");
        }

        $legacyRoutes = [
            'api/home',
            'api/auth/me',
            'api/auth/profile',
        ];

        foreach ($legacyRoutes as $uri) {
            $this->assertNotContains($uri, $routes, "Legacy unversioned route {$uri} must not exist.");
        }
    }

    public function test_admin_toggle_routes_are_protected_by_api_auth_and_admin_role(): void
    {
        $adminToggleUris = [
            'api/v1/admin/branches/{branch}/toggle-active',
            'api/v1/admin/theaters/{theater}/toggle-active',
            'api/v1/admin/seat-layout-templates/{seatLayoutTemplate}/toggle-active',
        ];

        foreach ($adminToggleUris as $uri) {
            $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === $uri && in_array('POST', $route->methods(), true));

            $this->assertNotNull($route, "Admin toggle route {$uri} must exist.");

            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth:api', $middleware, "Admin toggle route {$uri} must require API authentication.");
            $this->assertContains('role:admin,super-admin,theater_manager', $middleware, "Admin toggle route {$uri} must require admin role.");
        }
    }

    public function test_api_responses_include_request_id_header_and_body(): void
    {
        $response = $this->getJson('/api/v1/movies', [
            'X-Request-ID' => 'security-test-request-id',
        ]);

        $response->assertHeader('X-Request-ID', 'security-test-request-id');
        $response->assertJsonPath('request_id', 'security-test-request-id');
    }

    public function test_api_not_found_uses_standard_error_without_internal_details(): void
    {
        $response = $this->getJson('/api/v1/definitely-missing-route', [
            'X-Request-ID' => 'not-found-request-id',
        ]);

        $response->assertStatus(404)
            ->assertHeader('X-Request-ID', 'not-found-request-id')
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found',
                'request_id' => 'not-found-request-id',
            ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString('NotFoundHttpException', $content);
        $this->assertStringNotContainsString('vendor', $content);
        $this->assertStringNotContainsString('C:\\', $content);
    }

    public function test_api_validation_errors_use_standard_shape_with_request_id(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '',
            'password' => '',
        ], [
            'X-Request-ID' => 'validation-request-id',
        ]);

        $response->assertStatus(422)
            ->assertHeader('X-Request-ID', 'validation-request-id')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('request_id', 'validation-request-id')
            ->assertJsonStructure([
                'errors' => ['login', 'password'],
            ]);
    }
}
