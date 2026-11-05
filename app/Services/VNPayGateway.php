<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\PaymentGatewayException;
use Throwable;

class VNPayGateway implements PaymentGatewayInterface
{
    private string $tmnCode;
    private string $hashSecret;
    private string $url;
    private string $returnUrl;

    public function __construct(
        ?string $tmnCode = null,
        ?string $hashSecret = null,
        ?string $url = null,
        ?string $returnUrl = null,
    ) {
        $this->tmnCode = $tmnCode ?? (string) config('services.vnpay.tmn_code', 'SANDBOX_TMN');
        $this->hashSecret = $hashSecret ?? (string) config('services.vnpay.hash_secret', 'SANDBOX_HASH_SECRET');
        $this->url = $url ?? (string) config('services.vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->returnUrl = $returnUrl ?? (string) config('services.vnpay.return_url', url('/payment/vnpay/callback'));
    }

    public function createPaymentLink(array $payload, ?string $clientIp = null): array
    {
        try {
            $orderCode = (string) ($payload['orderCode'] ?? '');
            $amount = (int) round(((float) ($payload['amount'] ?? 0)) * 100);
            $description = (string) ($payload['description'] ?? ('Thanh toan don hang ' . $orderCode));
            $returnUrl = (string) ($payload['returnUrl'] ?? $this->returnUrl);
            $clientIp = $clientIp ?: request()?->ip() ?: '127.0.0.1';

            $now = now();
            $createDate = $now->format('YmdHis');
            $expireDate = $now->copy()->addMinutes(15)->format('YmdHis');

            $vnpParams = [
                'vnp_Version' => '2.1.0',
                'vnp_TmnCode' => $this->tmnCode,
                'vnp_Amount' => (string) $amount,
                'vnp_Command' => 'pay',
                'vnp_CreateDate' => $createDate,
                'vnp_CurrCode' => 'VND',
                'vnp_IpAddr' => $clientIp,
                'vnp_Locale' => 'vn',
                'vnp_OrderInfo' => $description,
                'vnp_OrderType' => 'other',
                'vnp_ReturnUrl' => $returnUrl,
                'vnp_TxnRef' => $orderCode,
                'vnp_ExpireDate' => $expireDate,
            ];

            ksort($vnpParams);

            $query = '';
            $hashData = '';
            $i = 0;

            foreach ($vnpParams as $key => $value) {
                if ($i === 1) {
                    $hashData .= '&' . urlencode((string) $key) . '=' . urlencode((string) $value);
                } else {
                    $hashData .= urlencode((string) $key) . '=' . urlencode((string) $value);
                    $i = 1;
                }
                $query .= urlencode((string) $key) . '=' . urlencode((string) $value) . '&';
            }

            $vnpSecureHash = hash_hmac('sha512', $hashData, $this->hashSecret);
            $checkoutUrl = $this->url . '?' . $query . 'vnp_SecureHash=' . $vnpSecureHash;

            return [
                'checkoutUrl' => $checkoutUrl,
                'orderCode' => $orderCode,
            ];
        } catch (Throwable $e) {
            throw new PaymentGatewayException('Không thể tạo link thanh toán VNPay: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function verifyCallback(array $params): bool
    {
        $vnpSecureHash = (string) ($params['vnp_SecureHash'] ?? '');

        if ($vnpSecureHash === '') {
            return false;
        }

        $inputData = [];
        foreach ($params as $key => $value) {
            if (str_starts_with((string) $key, 'vnp_') && !in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true)) {
                $inputData[$key] = $value;
            }
        }

        ksort($inputData);

        $hashData = '';
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i === 1) {
                $hashData .= '&' . urlencode((string) $key) . '=' . urlencode((string) $value);
            } else {
                $hashData .= urlencode((string) $key) . '=' . urlencode((string) $value);
                $i = 1;
            }
        }

        $calculatedHash = hash_hmac('sha512', $hashData, $this->hashSecret);

        return hash_equals(strtolower($calculatedHash), strtolower($vnpSecureHash));
    }

    public function verifyWebhook(array $data): array
    {
        if (!$this->verifyCallback($data)) {
            throw new PaymentGatewayException('Chữ ký VNPay không hợp lệ.');
        }

        $responseCode = (string) ($data['vnp_ResponseCode'] ?? '');
        $status = match ($responseCode) {
            '00' => 'PAID',
            '24' => 'CANCELLED',
            default => 'FAILED',
        };

        return [
            'orderCode' => $data['vnp_TxnRef'] ?? null,
            'status' => $status,
            'responseCode' => $responseCode,
            'amount' => isset($data['vnp_Amount']) ? ((float) $data['vnp_Amount']) / 100 : 0.0,
            'transactionNo' => $data['vnp_TransactionNo'] ?? null,
            'bankCode' => $data['vnp_BankCode'] ?? null,
            'payDate' => $data['vnp_PayDate'] ?? null,
            'orderInfo' => $data['vnp_OrderInfo'] ?? null,
        ];
    }

    public function getPaymentInfo(int|string $orderCode): array
    {
        return [
            'orderCode' => $orderCode,
            'status' => 'PENDING',
        ];
    }
}
