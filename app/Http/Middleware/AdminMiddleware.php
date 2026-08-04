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
            if ($request->expectsJson()) {
                return $this->errorResponse('Unauthenticated', 401);
            }

            return redirect()->route('login');
        }

        if (!$user->canAccessAdminPanel()) {
            if ($request->expectsJson()) {
                return $this->errorResponse('Forbidden: management role required', 403);
            }

            return redirect()->route('home')->with('error', 'B?n kh?ng c? quy?n truy c?p trang qu?n l?.');
        }

        return $next($request);
    }
}
