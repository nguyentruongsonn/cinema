<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CookieToBearerToken
{
    /**
     * Handle an incoming request.
     * Convert access_token cookie to Authorization Bearer header.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->bearerToken() && $request->cookies->has('access_token')) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie('access_token'));
        }

        return $next($request);
    }
}
