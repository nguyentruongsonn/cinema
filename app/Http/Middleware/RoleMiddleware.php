<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * Usage:
     * - middleware('role:admin')
     * - middleware('role:admin,manager')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        if (empty($roles)) {
            return $this->errorResponse('Forbidden: role is required', 403);
        }

        if (!$user->hasAnyRole($roles)) {
            return $this->errorResponse('Forbidden: insufficient role', 403);
        }

        return $next($request);
    }
}
