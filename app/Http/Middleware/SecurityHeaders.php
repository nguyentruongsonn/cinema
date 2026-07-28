<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply to non-API responses to avoid breaking API clients
        if ($request->is('api/*')) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0');

        // Content Security Policy
        $reverbEnabled = (bool) env('REVERB_ENABLED', false);
        $reverbHost = trim((string) env('REVERB_HOST', 'localhost'));
        $reverbPort = (int) env('REVERB_PORT', 8080);
        $reverbScheme = env('REVERB_SCHEME', 'http') === 'https' ? 'wss' : 'ws';
        $reverbOrigin = $reverbEnabled && $reverbHost !== ''
            ? sprintf('%s://%s:%d', $reverbScheme, $reverbHost, $reverbPort)
            : null;
        $connectSources = [
            "'self'",
            'https://api-merchant.payos.vn',
            'https://api.payos.vn',
            'https://cdn.jsdelivr.net',
        ];
        if ($reverbOrigin) {
            $connectSources[] = $reverbOrigin;
            $reverbWsScheme = $reverbScheme === 'wss' ? 'ws' : 'wss';
            $connectSources[] = sprintf('%s://%s:%d', $reverbWsScheme, $reverbHost, $reverbPort);
        }

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "img-src 'self' data: https: blob:",
            'connect-src ' . implode(' ', $connectSources),
            "frame-src 'self' https://sandbox.vnpayment.vn",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        return $response;
    }
}
