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
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(), microphone=(), payment=(self), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->is(
            'api/v1/auth/*',
            'api/v1/admin/*',
            'api/v1/pos/*',
            'api/v1/orders/*',
            'api/v1/payments/*',
            'api/v1/tickets/*'
        )) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->is('api/*')) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '0');

        // Content Security Policy
        $reverbOptions = (array) config('broadcasting.connections.reverb.options', []);
        $reverbEnabled = config('broadcasting.default') === 'reverb';
        $reverbHost = trim((string) ($reverbOptions['host'] ?? 'localhost'));
        $reverbPort = (int) ($reverbOptions['port'] ?? 8080);
        $reverbScheme = ($reverbOptions['scheme'] ?? 'http') === 'https' ? 'wss' : 'ws';
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
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "style-src-attr 'unsafe-inline'",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "img-src 'self' data: https: blob:",
            'connect-src '.implode(' ', $connectSources),
            "frame-src 'self' https://sandbox.vnpayment.vn",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        $strictStyleCsp = array_map(
            static fn (string $directive): string => $directive === "style-src-attr 'unsafe-inline'"
                ? "style-src-attr 'none'"
                : $directive,
            $csp,
        );
        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', $strictStyleCsp));

        return $response;
    }
}
