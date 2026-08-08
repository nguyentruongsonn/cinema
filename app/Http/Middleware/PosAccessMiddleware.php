<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * PosAccessMiddleware
 *
 * Restricts /pos routes to authenticated POS staff and administrators.
 * Redirects other roles to their appropriate landing page.
 */
class PosAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try cookie-based auth first (web route), then API guard
        $user = Auth::user() ?? Auth::guard('api')->user();

        if (!$user) {
            return redirect('/login')->with('error', 'Vui lòng đăng nhập để sử dụng POS.');
        }

        if (! $user->hasAnyRole(['ticket_seller', 'admin', 'super-admin'])) {
            return redirect('/')->with('error', 'Bạn không có quyền truy cập hệ thống POS.');
        }

        if ($user->hasRole('ticket_seller') && ! $user->theaters()->exists()) {
            return redirect('/login')->with('error', 'Bạn chưa được phân công rạp chiếu. Vui lòng liên hệ quản lý.');
        }

        return $next($request);
    }
}
