<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * Usage:
     * - middleware('permission:view_movies')
     * - middleware('permission:create_movies,edit_movies')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        if (empty($permissions)) {
            return $this->errorResponse('Forbidden: permission is required', 403);
        }

        // Check if user has ANY of the required permissions
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return $this->errorResponse('Forbidden: insufficient permissions', 403);
    }
}
