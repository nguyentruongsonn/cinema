<?php

namespace App\Services\PayOS;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayOSService
{
    private string $clientId;
    private string $apiKey;
    private string $checksumKey;
    private string $apiUrl;
    private string $returnUrl;
    private string $cancelUrl;

    public function __construct()
    {
        $this->clientId = config('services.payos.client_id');
        $this->apiKey = config('services.payos.api_key');
        $this->checksumKey = config('services.payos.checksum_key');
        $this->returnUrl = config('services.payos.return_url');
        $this->cancelUrl = config('services.payos.cancel_url');
        $this->apiUrl = config('services.payos.api_url');
    }

    /**
     * Create payment link
     */
    public function createPaymentLink(array $params): array
    {
        $orderCode = $params['order_code'];
        $amount = (int) $params['amount'];
        $description = $params['description'] ?? "Thanh toan don hang #{$orderCode}";
        
        $data = [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'description' => $description,
            'returnUrl' => $this->returnUrl,
            'cancelUrl' => $this->cancelUrl,
        ];

        $signature = PayOSSignature::generatePaymentLinkSignature(
            $amount,
            $this->cancelUrl,
            $description,
            $orderCode,
            $this->returnUrl,
            $this->checksumKey
        );

        try {
            $response = Http::withHeaders([
                'x-client-id' => $this->clientId,
                'x-api-key' => $this->apiKey,
            ])->post("{$this->apiUrl}/v2/payment-requests", array_merge($data, [
                'signature' => $signature
            ]));

            if (!$response->successful()) {
                throw new \Exception($response->json('message') ?? 'PayOS API error');
            }

            $result = $response->json('data');
            
            return [
                'checkout_url' => $result['checkoutUrl'] ?? null,
                'qr_code' => $result['qrCode'] ?? null,
                'payment_link_id' => $result['paymentLinkId'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('PayOS create payment link error', [
                'error' => $e->getMessage(),
                'order_code' => $orderCode
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook from PayOS
     */
    public function verifyWebhook(array $webhookData): array
    {
        if (!PayOSSignature::verifyWebhookSignature($webhookData, $this->checksumKey)) {
            throw new \Exception('Invalid signature');
        }

        $data = $webhookData['data'] ?? [];
        
        return [
            'order_code' => $data['orderCode'] ?? null,
            'amount' => $data['amount'] ?? 0,
            'status' => ($webhookData['code'] ?? '') === '00' ? 'completed' : 'failed',
            'transaction_ref' => $data['reference'] ?? null,
            'payment_time' => $data['transactionDateTime'] ?? null,
        ];
    }
}