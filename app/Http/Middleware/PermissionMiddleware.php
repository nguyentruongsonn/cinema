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

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::user();

        if (!$user) {
            if (! $request->expectsJson()) {
                return redirect()->route('login');
            }
            return $this->errorResponse('Unauthenticated', 401);
        }

        if (empty($permissions)) {
            if (! $request->expectsJson()) {
                return redirect()->route('home')->with('error', 'Quyền truy cập không hợp lệ.');
            }
            return $this->errorResponse('Forbidden: permission is required', 403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route($user->adminLandingRouteName())
                ->with('error', 'Bạn không có quyền truy cập chức năng này.');
        }

        return $this->errorResponse('Forbidden: insufficient permissions', 403);
    }
}
