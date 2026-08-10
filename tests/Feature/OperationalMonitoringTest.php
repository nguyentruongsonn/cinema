<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationalMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('queue.monitoring.queues', ['default']);
        config()->set('observability.operations.lookback_hours', 24);
        config()->set('observability.operations.email_max_age_seconds', 60);
        config()->set('observability.operations.max_overdue_payments', 0);
        config()->set('observability.operations.max_unsent_ticket_emails', 0);
        config()->set('observability.alerts.enabled', true);
        config()->set('observability.alerts.webhook_url', 'https://alerts.example.test/hook');
        config()->set('observability.alerts.cooldown_seconds', 300);
    }

    public function test_healthy_operations_do_not_send_an_alert(): void
    {
        Http::fake();

        $this->artisan('operations:monitor-health --json')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_unhealthy_operations_alert_once_during_cooldown(): void
    {
        Http::fake(['https://alerts.example.test/*' => Http::response(['ok' => true])]);

        Order::factory()->create([
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'paid_at' => now()->subMinutes(10),
            'ticket_email_sent_at' => null,
            'created_at' => now()->subMinutes(10),
        ]);

        $this->artisan('operations:monitor-health --json')->assertExitCode(1);
        $this->artisan('operations:monitor-health --json')->assertExitCode(1);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://alerts.example.test/hook'
            && $request['event'] === 'cinema.operational_health_failed'
            && $request['health']['business']['checks']['unsent_ticket_emails']['count'] === 1);
    }

    public function test_overdue_pending_payment_is_reported(): void
    {
        Http::fake();

        Order::factory()->create([
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'expired_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(20),
        ]);

        $this->artisan('operations:monitor-health --json')
            ->expectsOutputToContain('"overdue_pending_payments":{"count":1')
            ->assertExitCode(1);
    }
}
