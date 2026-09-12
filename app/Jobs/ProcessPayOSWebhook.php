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

    public $tries = 3;

    public int $timeout = 60;

    protected array $webhookData;

    public function __construct(array $webhookData)
    {
        $this->webhookData = $webhookData;
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        $orderCode = data_get($this->webhookData, 'data.orderCode', 'unknown');

        return 'payos-webhook:'.$orderCode;
    }

    public function middleware(): array
    {
        $orderCode = data_get($this->webhookData, 'data.orderCode', 'unknown');

        return [
            (new WithoutOverlapping('payos-webhook:'.$orderCode))
                ->expireAfter(300)
                ->releaseAfter(10),
        ];
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function tags(): array
    {
        return [
            'payments',
            'payos',
            'webhook',
            'order:'.data_get($this->webhookData, 'data.orderCode', 'unknown'),
        ];
    }

    public function handle(PaymentService $paymentService): void
    {
        try {
            Log::info('Processing PayOS webhook job', [
                'order_code' => data_get($this->webhookData, 'data.orderCode'),
                'payment_status' => data_get($this->webhookData, 'data.status'),
                'webhook_code' => data_get($this->webhookData, 'code'),
                'attempt' => $this->attempts(),
            ]);

            $paymentService->handleWebhook($this->webhookData);

            Log::info('PayOS webhook processed successfully', [
                'order_code' => data_get($this->webhookData, 'data.orderCode'),
                'payment_status' => data_get($this->webhookData, 'data.status'),
            ]);
        } catch (\Throwable $e) {
            Log::error('PayOS webhook processing failed', [
                'order_code' => data_get($this->webhookData, 'data.orderCode'),
                'attempt' => $this->attempts(),
                'error_class' => get_class($e),
                'error_code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $orderCode = data_get($this->webhookData, 'data.orderCode');

        Log::critical('PayOS webhook job failed after all retries', [
            'order_code' => $orderCode,
            'payment_status' => data_get($this->webhookData, 'data.status'),
            'error_class' => get_class($exception),
            'final_attempt' => $this->attempts(),
            'recovery_note' => 'Check idempotency_keys table for failed webhook record',
        ]);
    }
}
