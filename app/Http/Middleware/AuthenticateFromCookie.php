<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\JWTAuth;

class AuthenticateFromCookie
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly JWTAuth $jwt
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('access_token');
        $refreshToken = $request->cookie('refresh_token');
        $newTokenResult = null;

        if ($token) {
            try {
                $user = $this->jwt->setToken($token)->authenticate();
                $this->setUser($user);
            } catch (TokenExpiredException $e) {
                if ($refreshToken) {
                    try {
                        $newTokenResult = $this->authService->refreshAccessToken($refreshToken, $request->ip(), $request->userAgent());
                        $this->setUser($newTokenResult['user']);
                    } catch (\Exception $refreshEx) {
                        Log::debug('SSR Auth - Refresh failed: '.$refreshEx->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::debug('SSR auth error: '.$e->getMessage());
            }
        } elseif ($refreshToken) {
            try {
                $newTokenResult = $this->authService->refreshAccessToken($refreshToken, $request->ip(), $request->userAgent());
                $this->setUser($newTokenResult['user']);
            } catch (\Exception $e) {
                Log::debug('SSR Auth - Refresh fallback failed: '.$e->getMessage());
            }
        }

        $response = $next($request);

        if ($newTokenResult && $response instanceof Response) {
            $response->headers->setCookie(cookie(
                'access_token', $newTokenResult['access_token'], (int) ceil($newTokenResult['expires_in'] / 60), '/', config('session.domain'), config('session.secure'), true, false, config('session.same_site', 'lax')
            ));
            $response->headers->setCookie(cookie(
                'refresh_token', $newTokenResult['refresh_token'], (int) ceil($newTokenResult['refresh_expires_in'] / 60), '/', config('session.domain'), config('session.secure'), true, false, config('session.same_site', 'lax')
            ));
        }

        return $response;
    }

    private function setUser($user): void
    {
        if ($user) {
            Auth::guard('web')->setUser($user);
            Auth::guard('api')->setUser($user);
        }
    }
}
