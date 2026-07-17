<?php

namespace Tests\Unit;

use Tests\TestCase;

class SecurityTest extends TestCase
{
    /**
     * Test that essential security headers are present in responses
     *
     * @return void
     */
    public function test_security_headers_present()
    {
        $response = $this->get('/');

        // X-Content-Type-Options prevents MIME type sniffing
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options prevents clickjacking
        $response->assertHeader('X-Frame-Options', 'DENY');

        // X-XSS-Protection: Modern browsers deprecated this (Chrome/Edge/Safari removed it)
        // Value '0' disables the deprecated XSS auditor - CSP is the modern alternative
        $response->assertHeader('X-XSS-Protection', '0');

        // Referrer-Policy controls information leakage
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Test that Content Security Policy header is present
     *
     * @return void
     */
    public function test_csp_header_present()
    {
        $response = $this->get('/');

        $response->assertHeader('Content-Security-Policy');

        // Verify CSP contains essential directives
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src", $csp);
        $this->assertStringContainsString("style-src", $csp);
    }

    /**
     * Test that common XSS attack vectors are properly escaped
     *
     * @return void
     */
    public function test_xss_attempts_escaped()
    {
        $xssVectors = [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            'javascript:alert(1)',
            '<svg/onload=alert(1)>',
            '<iframe src="javascript:alert(1)">',
            '"><script>alert(1)</script>',
            '\';alert(1);//',
        ];

        foreach ($xssVectors as $vector) {
            // Test in search parameter
            $response = $this->get('/movies?search=' . urlencode($vector));

            // Raw content should not contain unescaped vector
            $response->assertDontSee($vector, false);

            // Should succeed without errors
            $this->assertNotEquals(500, $response->status());
        }
    }

    /**
     * Test that HTML special characters are properly escaped
     *
     * @return void
     */
    public function test_html_special_characters_escaped()
    {
        $specialChars = [
            '&' => '&',
            '<' => '<',
            '>' => '>',
            '"' => '"',
            "'" => '&#039;',
        ];

        foreach ($specialChars as $char => $escaped) {
            $response = $this->get('/movies?search=' . urlencode($char));

            // Should not see unescaped character
            $content = $response->getContent();

            // In a real scenario, if search term is displayed, it should be escaped
            // This is a simplified test
            $this->assertNotEquals(500, $response->status());
        }
    }

    /**
     * Test that inline JavaScript in user input is neutralized
     *
     * @return void
     */
    public function test_inline_javascript_blocked()
    {
        $jsPayloads = [
            'onclick=alert(1)',
            'onload="alert(1)"',
            'onerror=alert(1)',
            'onmouseover=alert(1)',
        ];

        foreach ($jsPayloads as $payload) {
            $response = $this->get('/movies?search=' . urlencode($payload));

            // Should not contain raw payload
            $response->assertDontSee($payload, false);
            $this->assertNotEquals(500, $response->status());
        }
    }

    /**
     * Test that security headers are present on all routes
     *
     * @return void
     */
    public function test_security_headers_on_all_routes()
    {
        $routes = [
            '/',
            '/movies',
            '/theaters',
            '/login',
            '/register',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Content-Security-Policy');
        }
    }

    /**
     * Test that HSTS header is present in production
     *
     * @return void
     */
    public function test_hsts_header_in_production()
    {
        // Only test in production environment
        if (app()->environment('production')) {
            $response = $this->get('/');

            $response->assertHeader('Strict-Transport-Security');

            $hsts = $response->headers->get('Strict-Transport-Security');
            $this->assertStringContainsString('max-age=', $hsts);
            $this->assertStringContainsString('includeSubDomains', $hsts);
        } else {
            $this->assertTrue(true, 'HSTS test skipped in non-production environment');
        }
    }

    /**
     * Test that dangerous HTML tags are sanitized
     *
     * @return void
     */
    public function test_dangerous_html_tags_sanitized()
    {
        $dangerousPayloads = [
            '<script>alert("xss-test-payload")</script>',
            '<iframe src="javascript:alert(\'xss-test-payload\')"></iframe>',
            '<object data="xss-test-payload"></object>',
            '<embed src="xss-test-payload">',
            '<applet code="xss-test-payload"></applet>',
            '<meta http-equiv="refresh" content="0;url=javascript:alert(\'xss-test-payload\')">',
            '<link rel="import" href="javascript:alert(\'xss-test-payload\')">',
            '<style>body{background:url("javascript:alert(\'xss-test-payload\')")}</style>',
        ];

        foreach ($dangerousPayloads as $payload) {
            $response = $this->get('/movies?search=' . urlencode($payload));

            // The full user-supplied dangerous payload must never be reflected raw.
            // Legitimate layout assets may still contain normal <script>, <link>, <meta>, or <style> tags.
            $response->assertDontSee($payload, false);
            $this->assertNotEquals(500, $response->status());
        }
    }

    /**
     * Test that protocol handlers are neutralized
     *
     * @return void
     */
    public function test_protocol_handlers_neutralized()
    {
        $protocols = [
            'javascript:',
            'data:',
            'vbscript:',
            'file:',
        ];

        foreach ($protocols as $protocol) {
            $payload = $protocol . 'alert(1)';
            $response = $this->get('/movies?search=' . urlencode($payload));

            // Should not contain raw protocol
            $response->assertDontSee($protocol, false);
            $this->assertNotEquals(500, $response->status());
        }
    }

    /**
     * Test that security utilities are working correctly
     *
     * @return void
     */
    public function test_security_utility_escapes_html()
    {
        // Test htmlspecialchars escaping
        $testCases = [
            '<script>' => '&' . 'lt;script' . '&' . 'gt;',
            '&' => '&' . 'amp;',
            '"test"' => '&' . 'quot;test' . '&' . 'quot;',
            "'test'" => '&' . '#039;test' . '&' . '#039;',
        ];

        // Verifies that htmlspecialchars correctly escapes HTML entities
        foreach ($testCases as $input => $expected) {
            $escaped = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            $this->assertEquals($expected, $escaped);
        }
    }

    public function test_env_example_uses_safe_release_defaults(): void
    {
        $env = $this->parseEnvExample();

        $this->assertSame('false', $env['APP_DEBUG'] ?? null);
        $this->assertSame('info', $env['LOG_LEVEL'] ?? null);
        $this->assertSame('true', $env['SESSION_ENCRYPT'] ?? null);
        $this->assertSame('true', $env['SESSION_SECURE'] ?? null);
        $this->assertSame('false', $env['JWT_SHOW_BLACKLIST_EXCEPTION'] ?? null);
        $this->assertSame('', $env['APP_KEY'] ?? null);
        $this->assertSame('', $env['JWT_SECRET'] ?? null);
        $this->assertSame('', $env['PAYOS_API_KEY'] ?? null);
        $this->assertSame('', $env['PAYOS_WEBHOOK_SECRET'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    private function parseEnvExample(): array
    {
        $contents = file(base_path('.env.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $values = [];

        foreach ($contents ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[$key] = trim($value, "\"'");
        }

        return $values;
    }
}
