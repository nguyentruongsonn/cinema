<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TheaterScopeMiddleware
 *
 * Injects the authenticated user's assigned theater IDs into the request
 * so that controllers and services can scope data to the user's theaters.
 * Admin users bypass scoping entirely.
 */
class TheaterScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api') ?? $request->user();

        // Not authenticated – let auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // Admin: no scope restrictions
        if ($user->isAdmin()) {
            $request->attributes->set('actor_is_scoped', false);
            return $next($request);
        }

        // Staff roles that require theater scoping
        if ($user->requiresTheaterScope()) {
            $theaterIds = $user->theaters()->pluck('theaters.id')->toArray();

            if (empty($theaterIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chưa được phân công rạp chiếu. Vui lòng liên hệ quản trị viên.',
                ], 403);
            }

            $request->attributes->set('actor_theater_ids', $theaterIds);
            $request->attributes->set('actor_is_scoped', true);

            return $next($request);
        }

        // Other roles (customer, etc.) – no scope needed
        $request->attributes->set('actor_is_scoped', false);
        return $next($request);
    }
}
