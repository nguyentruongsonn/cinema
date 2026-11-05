<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function __construct(
        private readonly PayOSGateway $payOSGateway,
        private readonly VNPayGateway $vnPayGateway,
    ) {}

    public function make(?string $provider = null): PaymentGatewayInterface
    {
        $provider = strtolower(trim((string) $provider));

        return match ($provider) {
            'vnpay' => $this->vnPayGateway,
            'payos', '' => $this->payOSGateway,
            default => throw new InvalidArgumentException("Unsupported payment provider [{$provider}]."),
        };
    }
}
