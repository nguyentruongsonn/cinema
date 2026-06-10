<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('access_token');

        if ($token) {
            try {
                $user = JWTAuth::setToken($token)->authenticate();
                
                if ($user) {
                    // Set user for both guards to ensure SSR and API consistency
                    // Web guard: for Blade @auth, Auth::check() in views
                    // API guard: for API routes that use auth:api middleware
                    Auth::guard('web')->setUser($user);
                    Auth::guard('api')->setUser($user);
                }
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                // Token expired - frontend will handle refresh
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                // Invalid token - continue as guest
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                // JWT error - continue as guest
            } catch (\Exception $e) {
                // Unexpected error - log but continue as guest
                Log::debug('SSR auth error: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}