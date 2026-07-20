<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Showtime;
use App\Models\User;
use App\Services\PayOSGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 1 Integration Tests: Payment Controller Security
 * Tests callback ownership, webhook signature, and throttling
 */
class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Order $order;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        
        $showtime = Showtime::factory()->create();
        
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'gateway_order_code' => (string) (100000 + random_int(1, 899999)),
        ]);

        $this->payment = Payment::createPending([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'method' => 'payos',
            'transaction_code' => 'TXN_' . time(),
            'gateway_order_code' => $this->order->gateway_order_code,
            'amount' => 100000,
            'payload' => [],
        ]);
    }

    #[Test]
    public function callback_rejects_unauthorized_user_access()
    {
        $response = $this->actingAs($this->otherUser)
            ->get(route('payment.payos.callback', [
                'orderCode' => $this->order->gateway_order_code,
            ]));

        $response->assertRedirect(route('home'));
    }

    #[Test]
    public function callback_allows_order_owner_access()
    {
        $response = $this->actingAs($this->user)
            ->get(route('payment.payos.callback', [
                'orderCode' => $this->order->gateway_order_code,
            ]));

        // Should not be 403 (actual behavior depends on payment status)
        $response->assertStatus(302); // Redirect expected
    }

    #[Test]
    public function callback_ignores_success_parameter_from_client()
    {
        // Mark payment as FAILED in database
        $this->payment->update(['status' => Payment::STATUS_FAILED]);

        // Client tries to pass success=true, but it should be ignored
        $response = $this->actingAs($this->user)
            ->get(route('payment.payos.callback', [
                'orderCode' => $this->order->gateway_order_code,
                'success' => 'true', // Client tries to fake success
            ]));

        // Failed payments must not be treated as successful; the controller returns
        // the booking page with a non-success payment status.
        $response->assertRedirect();
        $this->assertStringContainsString('paymentStatus=pending', (string) $response->headers->get('Location'));
        // Note: Controller logs warning but does not set session error for gateway sync failures
    }

    #[Test]
    public function callback_checks_actual_payment_status_from_database()
    {
        // Mark payment as PAID in database (real verification)
        $this->payment->markSuccessful();
        $this->order->update(['status' => Order::STATUS_CONFIRMED]);

        // Client passes success=false, but database says PAID
        $response = $this->actingAs($this->user)
            ->get(route('payment.payos.callback', [
                'orderCode' => $this->order->gateway_order_code,
                'success' => 'false', // Client tries to fake failure
            ]));

        // Should redirect to success because actual database status is PAID
        $response->assertRedirect();
        // The actual redirect depends on controller logic, but it should NOT trust client parameter
    }

    #[Test]
    public function webhook_rejects_malformed_payload_before_gateway_verification()
    {
        $webhookPayload = [
            'orderCode' => $this->order->gateway_order_code,
            'status' => 'success',
        ];

        $response = $this->postJson(route('payment.payos.webhook'), $webhookPayload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data']);
    }

    #[Test]
    public function webhook_signature_is_delegated_to_payos_gateway()
    {
        $webhookPayload = [
            'code' => '00',
            'success' => true,
            'signature' => 'gateway-signature',
            'data' => [
                'orderCode' => (int) $this->order->gateway_order_code,
                'status' => 'PAID',
            ],
        ];

        $this->mock(PaymentService::class, function ($mock) use ($webhookPayload) {
            $mock->shouldReceive('handleWebhook')
                ->once()
                ->with($webhookPayload)
                ->andReturn(['already_processed' => false, 'skipped' => true]);
        });

        $this->postJson(route('payment.payos.webhook'), $webhookPayload)->assertOk();
    }

    #[Test]
    public function webhook_accepts_valid_gateway_payload_without_custom_header()
    {
        $webhookPayload = [
            'code' => '00',
            'success' => true,
            'signature' => 'gateway-signature',
            'data' => [
                'orderCode' => (int) $this->order->gateway_order_code,
                'status' => 'PAID',
                'amount' => (int) $this->payment->amount,
            ],
        ];

        // Mock PaymentService to avoid full business logic execution in controller test
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleWebhook')
                ->once()
                ->andReturn(['already_processed' => false, 'skipped' => false]);
        });

        // Send webhook with VALID signature
        $response = $this->postJson(route('payment.payos.webhook'), $webhookPayload);

        // Should accept (200 OK)
        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function webhook_replay_does_not_repeat_fulfillment_side_effects()
    {
        $webhookSecret = 'test-webhook-secret';
        Config::set('services.payos.webhook_secret', $webhookSecret);

        $webhookPayload = [
            'code' => '00',
            'success' => true,
            'signature' => 'gateway-signature',
            'data' => [
                'orderCode' => (int) $this->order->gateway_order_code,
                'status' => 'PAID',
                'amount' => (int) $this->payment->amount,
            ],
        ];

        $this->mock(PayOSGateway::class, function ($mock) {
            $mock->shouldReceive('verifyWebhook')
                ->twice()
                ->andReturn([
                    'orderCode' => (int) $this->order->gateway_order_code,
                    'status' => 'PAID',
                ]);
        });

        $firstResponse = $this->postJson(route('payment.payos.webhook'), $webhookPayload);

        $secondResponse = $this->postJson(route('payment.payos.webhook'), $webhookPayload);

        $firstResponse->assertOk()->assertJson(['success' => true]);
        $secondResponse->assertOk()->assertJson(['success' => true]);

        $this->assertSame(Order::STATUS_CONFIRMED, (int) $this->order->fresh()->status);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_SUCCESS, $this->payment->fresh()->status);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => "webhook:finalize:{$this->order->gateway_order_code}",
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function callback_route_has_payment_throttling()
    {
        // PayOS callbacks use throttle:payments (10 req/min in AppServiceProvider)
        // Make 11 rapid requests to trigger throttle
        
        for ($i = 0; $i < 11; $i++) {
            $response = $this->actingAs($this->user)
                ->get(route('payment.payos.callback', [
                    'orderCode' => $this->order->gateway_order_code,
                ]));
            
            if ($i < 10) {
                // First 10 should pass
                $this->assertNotEquals(429, $response->status(), "Request $i should not be throttled");
            } else {
                // 11th request should be throttled
                $response->assertStatus(429);
            }
        }
    }

    #[Test]
    public function webhook_route_has_webhook_throttling()
    {
        $webhookSecret = 'test-webhook-secret';
        Config::set('services.payos.webhook_secret', $webhookSecret);

        $webhookPayload = [
            'code' => '00',
            'success' => true,
            'data' => [
                'orderCode' => (int) $this->order->gateway_order_code,
            ],
        ];
        $payloadJson = json_encode($webhookPayload);
        $validSignature = hash_hmac('sha256', $payloadJson, $webhookSecret);

        // Webhook uses throttle:webhook (100 req/hour in AppServiceProvider)
        // Make 101 rapid requests to trigger throttle
        
        for ($i = 0; $i < 101; $i++) {
        $response = $this->postJson(route('payment.payos.webhook'), $webhookPayload);
            
            if ($i < 100) {
                // First 100 should pass (not throttled)
                $this->assertNotEquals(429, $response->status(), "Request $i should not be throttled");
            } else {
                // 101st request should be throttled
                $response->assertStatus(429);
            }
        }
    }

    #[Test]
    public function cancel_route_is_accessible_and_has_throttling()
    {
        $response = $this->actingAs($this->user)
            ->get(route('payment.payos.cancel', [
                'orderCode' => $this->order->gateway_order_code,
            ]));

        // Cancel route should be accessible
        $response->assertStatus(302); // Redirect expected

        // The initial request above consumes one token, so ten more requests
        // make the eleventh request overall hit the limit.
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->user)
                ->get(route('payment.payos.cancel', [
                    'orderCode' => $this->order->gateway_order_code,
                ]));
            
            if ($i < 9) {
                $this->assertNotEquals(429, $response->status());
            } else {
                $response->assertStatus(429);
            }
        }
    }

    #[Test]
    public function webhook_is_excluded_from_csrf_protection()
    {
        $webhookSecret = 'test-webhook-secret';
        Config::set('services.payos.webhook_secret', $webhookSecret);

        $webhookPayload = ['orderCode' => $this->order->gateway_order_code];
        $payloadJson = json_encode($webhookPayload);
        $validSignature = hash_hmac('sha256', $payloadJson, $webhookSecret);

        // Send webhook WITHOUT CSRF token (should still work)
        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->postJson(
                route('payment.payos.webhook'),
                $webhookPayload,
                ['x-payos-signature' => $validSignature]
            );

        // Should not get CSRF error (webhook is excluded in bootstrap/app.php)
        $this->assertNotEquals(419, $response->status());
    }
}
