<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.queue', 'ok');
    }

    public function test_readiness_and_monitor_fail_when_a_ready_job_is_too_old(): void
    {
        config()->set('queue.default', 'database');
        config()->set('queue.monitoring.queues', ['broadcasts']);
        config()->set('queue.monitoring.max_depth', 100);
        config()->set('queue.monitoring.max_age_seconds', 60);
        config()->set('queue.monitoring.include_in_readiness', true);

        DB::table('jobs')->insert([
            'queue' => 'broadcasts',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(2)->getTimestamp(),
            'created_at' => now()->subMinutes(2)->getTimestamp(),
        ]);

        $this->getJson('/api/v1/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.queue', 'unavailable');

        $this->artisan('queue:monitor-health --json')
            ->assertExitCode(1);
    }

    public function test_production_readiness_rejects_non_durable_runtime_drivers(): void
    {
        config()->set('app.env', 'production');
        config()->set('queue.default', 'sync');
        config()->set('broadcasting.default', 'log');
        config()->set('queue.monitoring.include_in_readiness', false);

        $this->getJson('/api/v1/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.runtime', 'unavailable');
    }

    public function test_future_delayed_jobs_do_not_fail_readiness_but_expired_reservations_do(): void
    {
        config()->set('queue.default', 'database');
        config()->set('queue.monitoring.queues', ['broadcasts']);
        config()->set('queue.monitoring.max_age_seconds', 60);
        config()->set('queue.monitoring.include_in_readiness', true);

        DB::table('jobs')->insert([
            'queue' => 'broadcasts',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->addHour()->getTimestamp(),
            'created_at' => now()->subHours(2)->getTimestamp(),
        ]);

        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('checks.queue', 'ok');

        DB::table('jobs')->insert([
            'queue' => 'broadcasts',
            'payload' => '{}',
            'attempts' => 1,
            'reserved_at' => now()->subMinutes(2)->getTimestamp(),
            'available_at' => now()->subMinutes(3)->getTimestamp(),
            'created_at' => now()->subMinutes(3)->getTimestamp(),
        ]);

        $this->getJson('/api/v1/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('checks.queue', 'unavailable');
    }

    public function test_queue_inspection_failure_fails_readiness_and_monitoring(): void
    {
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.table', 'missing_jobs_table');
        config()->set('queue.monitoring.queues', ['broadcasts']);
        config()->set('queue.monitoring.include_in_readiness', true);

        $this->getJson('/api/v1/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.queue', 'unavailable');

        $this->artisan('queue:monitor-health --json')
            ->assertExitCode(1);
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
