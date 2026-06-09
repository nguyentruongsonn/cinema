<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPayOSWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The webhook payload data.
     *
     * @var array
     */
    protected $webhookData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $webhookData)
    {
        $this->webhookData = $webhookData;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentService $paymentService): void
    {
        try {
            Log::info('Processing PayOS webhook job', [
                'order_code' => $this->webhookData['data']['orderCode'] ?? null,
                'attempt' => $this->attempts(),
            ]);

            $paymentService->handleWebhook($this->webhookData);

            Log::info('PayOS webhook processed successfully', [
                'order_code' => $this->webhookData['data']['orderCode'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('PayOS webhook processing failed', [
                'order_code' => $this->webhookData['data']['orderCode'] ?? null,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('PayOS webhook job failed after all retries', [
            'order_code' => $this->webhookData['data']['orderCode'] ?? null,
            'error' => $exception->getMessage(),
            'webhook_data' => $this->webhookData,
        ]);

        // TODO: Send alert to monitoring system (e.g., Sentry, Slack)
        // TODO: Store in failed_webhooks table for manual review
    }
}
