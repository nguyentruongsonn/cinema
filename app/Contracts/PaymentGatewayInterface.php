<?php

declare(strict_types=1);

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function createPaymentLink(array $payload, ?string $clientIp = null): array;

    public function verifyWebhook(array $data): array;

    public function verifyCallback(array $params): bool;

    public function getPaymentInfo(int|string $orderCode): array;
}
