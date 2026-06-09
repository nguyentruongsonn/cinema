<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders Middleware
 *
 * Adds security-related HTTP headers to protect against common web vulnerabilities.
 * This provides defense-in-depth alongside application-level security measures.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // Enable browser XSS protection (legacy but still useful)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature-Policy)
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Force HTTPS in production (uncomment when deployed with SSL)
        // Commented out for development environments without HTTPS
        // if (app()->environment('production')) {
        //     $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        // }

        // Content Security Policy
        $response->headers->set('Content-Security-Policy', $this->getContentSecurityPolicy());

        return $response;
    }

    /**
     * Generate Content Security Policy header value.
     *
     * Balanced CSP that protects against XSS while allowing necessary resources.
     * Adjust as needed based on your application's requirements.
     */
    private function getContentSecurityPolicy(): string
    {
        $csp = [
            // Scripts: Allow self + specific trusted CDNs
            "default-src 'self'",

            // Scripts: Allow self, inline scripts (for Blade), and trusted CDNs
            // Note: 'unsafe-inline' reduces CSP protection but is needed for inline scripts
            // Consider using nonces in production for better security
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://api-merchant.payos.vn",

            // Styles: Allow self, inline styles, and CDNs
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",

            // Images: Allow self, data URIs, and external sources
            "img-src 'self' data: https: http:",

            // Fonts: Allow self and Google Fonts
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",

            // AJAX/Fetch: Allow self and PayOS API
            "connect-src 'self' https://api-merchant.payos.vn https://api.payos.vn",

            // Frames: Block all frames (preventing clickjacking)
            "frame-ancestors 'none'",

            // Object/Embed: Block plugins
            "object-src 'none'",

            // Base URI: Restrict base tag
            "base-uri 'self'",

            // Forms: Allow posting to self only
            "form-action 'self'",

            // Upgrade insecure requests (uncomment in production with HTTPS)
            // "upgrade-insecure-requests",
        ];

        return implode('; ', $csp);
    }
}
