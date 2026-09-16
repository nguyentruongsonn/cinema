<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPayOSWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The webhook payload data.
     */
    protected array $webhookData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $webhookData)
    {
        $this->webhookData = $webhookData;
        $this->onQueue('payments');
    }

    /**
     * Get the unique ID for the job to prevent duplicate processing.
     */
    public function uniqueId(): string
    {
        $orderCode = data_get($this->webhookData, 'data.orderCode', 'unknown');

        return 'payos-webhook:'.$orderCode;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        $orderCode = data_get($this->webhookData, 'data.orderCode', 'unknown');

        return [
            (new WithoutOverlapping('payos-webhook:'.$orderCode))
                ->expireAfter(300)
                ->releaseAfter(10),
        ];
    }

    /**
     * Define exponential backoff for retries.
     */
    public function backoff(): array
    {
        return [10, 60, 300]; // 10s, 1min, 5min
    }

    /**
     * Get the tags that should be assigned to the job for monitoring.
     */
    public function tags(): array
    {
        return [
            'payments',
            'payos',
            'webhook',
            'order:'.data_get($this->webhookData, 'data.orderCode', 'unknown'),
        ];
    }

    /**
     * Execute the job.
     *
     * PHASE 1.6: Webhook idempotency and failure handling
     * - Redact sensitive payload before logging
     * - Rely on OrderFulfillmentService persistent idempotency
     * - Store failures in IdempotencyKey for manual recovery
     */
    public function handle(PaymentService $paymentService): void
    {
        try {
            // PHASE 1.6: Safe logging - redact sensitive fields, log only whitelisted data
            Log::info('Processing PayOS webhook job', [
                'order_code' => data_get($this->webhookData, 'data.orderCode'),
                'payment_status' => data_get($this->webhookData, 'data.status'),
                'webhook_code' => data_get($this->webhookData, 'code'),
                'attempt' => $this->attempts(),
                // Do NOT log full payload - may contain payment details
            ]);

            // OrderFulfillmentService::finalize() provides persistent idempotency
            // via IdempotencyKey with key format: "webhook:finalize:{gatewayOrderCode}"
            $paymentService->handleWebhook($this->webhookData);

            Log::info('PayOS webhook processed successfully', [
                'order_code' => data_get($this->webhookData, 'data.orderCode'),
                'payment_status' => data_get($this->webhookData, 'data.status'),
            ]);
        } catch (\Throwable $e) {
            // PHASE 1.6: Safe error logging - do not leak sensitive exception details
            Log::error('PayOS webhook processing failed', [
                'order_code' => data_get($this->webhookData, 'data.orderCode'),
                'attempt' => $this->attempts(),
                'error_class' => get_class($e),
                // Log error type/code only - full message may contain sensitive data
                'error_code' => $e->getCode(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure after all retry attempts are exhausted.
     *
     * PHASE 1.6: Failed webhook recovery
     * - Log critical failure for monitoring/alerting
     * - Failed webhooks are already stored in idempotency_keys table with status='failed'
     * - Admins can query idempotency_keys WHERE status='failed' AND request_path LIKE '%webhook%'
     * - Manual recovery: re-dispatch job or manually trigger PaymentService::handleWebhook()
     */
    public function failed(\Throwable $exception): void
    {
        $orderCode = data_get($this->webhookData, 'data.orderCode');

        // PHASE 1.6: Critical alert for monitoring systems
        Log::critical('PayOS webhook job failed after all retries', [
            'order_code' => $orderCode,
            'payment_status' => data_get($this->webhookData, 'data.status'),
            'error_class' => get_class($exception),
            'final_attempt' => $this->attempts(),
            // Failed webhook is already recorded in idempotency_keys table
            // by OrderFulfillmentService::finalize() with status='failed'
            'recovery_note' => 'Check idempotency_keys table for failed webhook record',
        ]);

        // PRODUCTION: Send alert to monitoring system
        // Examples:
        // - Sentry::captureException($exception, ['order_code' => $orderCode])
        // - Slack notification to #payment-alerts channel
        // - PagerDuty alert for on-call engineer
        // - Email notification to payment team

        // Manual recovery process:
        // 1. Query: SELECT * FROM idempotency_keys WHERE key LIKE 'webhook:finalize:%' AND status='failed'
        // 2. Verify order/payment state in database
        // 3. If payment was actually successful:
        //    - Call PaymentService::syncFromGateway($order) to retry gateway status check
        //    - Or manually update order/payment status after verification
        // 4. If payment failed legitimately:
        //    - Order will auto-expire via OrderExpirationService
        //    - User can create new order
    }
}
