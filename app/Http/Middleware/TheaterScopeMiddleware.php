<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TheaterScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api') ?? $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            $request->attributes->set('actor_is_scoped', false);
            return $next($request);
        }

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

        $request->attributes->set('actor_is_scoped', false);
        return $next($request);
    }
}
