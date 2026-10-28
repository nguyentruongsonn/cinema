<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use PayOS\PayOS;
use Throwable;

class PayOSGateway
{
    private PayOS $client;

    public function __construct()
    {
        $clientId = (string) (config('services.payos.client_id') ?: 'dummy-payos-client-id');
        $apiKey = (string) (config('services.payos.api_key') ?: 'dummy-payos-api-key');
        $checksumKey = (string) (config('services.payos.checksum_key') ?: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        $this->client = new PayOS(
            $clientId,
            $apiKey,
            $checksumKey,
        );
    }

    public function createPaymentLink(array $payload): array
    {
        try {
            return $this->client->createPaymentLink($payload);
        } catch (Throwable $e) {
            throw new PaymentGatewayException('Không thể tạo link thanh toán: ' . $e->getMessage());
        }
    }

    public function verifyWebhook(array $data): array
    {
        try {
            return $this->client->verifyPaymentWebhookData($data);
        } catch (Throwable $e) {
            throw new PaymentGatewayException('Webhook không hợp lệ: ' . $e->getMessage());
        }
    }

    public function getPaymentInfo(int $orderCode): array
    {
        try {
            return $this->client->getPaymentLinkInformation($orderCode);
        } catch (Throwable $e) {
            throw new PaymentGatewayException('Không thể lấy thông tin thanh toán: ' . $e->getMessage());
        }
    }
}
