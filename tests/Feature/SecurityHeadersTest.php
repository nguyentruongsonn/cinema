<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_api_responses_include_non_sniff_and_referrer_policy_headers(): void
    {
        $response = app(SecurityHeaders::class)->handle(
            Request::create('/api/v1/movies'),
            fn (): Response => response()->json(['success' => true]),
        );

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    public function test_html_responses_include_clickjacking_protection(): void
    {
        $response = app(SecurityHeaders::class)->handle(
            Request::create('/'),
            fn (): Response => new Response('<html></html>'),
        );

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("style-src-attr 'unsafe-inline'", $csp);

        $reportOnlyCsp = (string) $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("style-src-attr 'none'", $reportOnlyCsp);
        $this->assertStringNotContainsString("style-src-attr 'unsafe-inline'", $reportOnlyCsp);
    }
}
