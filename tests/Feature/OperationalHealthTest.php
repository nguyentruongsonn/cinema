<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_and_readiness_endpoints_are_safe_and_available(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertHeader('X-Request-ID');

        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok');
    }

    public function test_metrics_endpoint_is_private_and_exports_prometheus_text(): void
    {
        config()->set('observability.metrics_enabled', true);
        config()->set('observability.metrics_token', 'test-metrics-token');

        $this->get('/api/v1/internal/metrics')->assertNotFound();

        $this->withHeader('Authorization', 'Bearer test-metrics-token')
            ->get('/api/v1/internal/metrics')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('cinema_http_requests_total', false)
            ->assertSee('cinema_http_request_duration_milliseconds', false);
    }

    public function test_valid_caller_request_id_is_propagated(): void
    {
        $this->withHeader('X-Request-ID', 'checkout-test-123')
            ->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertHeader('X-Request-ID', 'checkout-test-123');
    }
}
