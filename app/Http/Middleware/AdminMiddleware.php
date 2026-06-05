<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            return $this->errorResponse('Forbidden: admin role required', 403);
        }

        return $next($request);
    }
}
