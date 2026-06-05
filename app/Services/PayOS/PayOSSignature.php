<?php

namespace App\Services\PayOS;

/**
 * PayOS Signature Helper
 * Handles signature generation and verification for PayOS payments
 */
class PayOSSignature
{
    /**
     * Generate signature for PayOS request
     * 
     * @param array $data Request data
     * @param string $checksumKey Checksum key from PayOS
     * @return string Generated signature
     */
    public static function generate(array $data, string $checksumKey): string
    {
        // Sort data by key
        ksort($data);
        
        // Convert to JSON string (no escaping slashes or unicode)
        $dataStr = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Generate HMAC SHA256 signature
        return hash_hmac('sha256', $dataStr, $checksumKey);
    }

    /**
     * Verify signature from PayOS callback
     * 
     * @param array $data Callback data
     * @param string $signature Signature from PayOS
     * @param string $checksumKey Checksum key from PayOS
     * @return bool True if signature is valid
     */
    public static function verify(array $data, string $signature, string $checksumKey): bool
    {
        // Generate expected signature
        $expectedSignature = self::generate($data, $checksumKey);
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate signature for payment link creation
     * PayOS requires specific field order for signature
     * 
     * @param int $amount Payment amount
     * @param string $cancelUrl Cancel URL
     * @param string $description Payment description
     * @param int $orderCode Order code
     * @param string $returnUrl Return URL
     * @param string $checksumKey Checksum key
     * @return string Generated signature
     */
    public static function generatePaymentLinkSignature(
        int $amount,
        string $cancelUrl,
        string $description,
        int $orderCode,
        string $returnUrl,
        string $checksumKey
    ): string {
        // PayOS requires exact field order
        $data = [
            'amount' => $amount,
            'cancelUrl' => $cancelUrl,
            'description' => $description,
            'orderCode' => $orderCode,
            'returnUrl' => $returnUrl,
        ];
        
        return self::generate($data, $checksumKey);
    }

    /**
     * Verify webhook signature
     * PayOS webhook sends data in specific format
     * 
     * @param array $webhookData Full webhook payload
     * @param string $checksumKey Checksum key
     * @return bool True if signature is valid
     */
    public static function verifyWebhookSignature(array $webhookData, string $checksumKey): bool
    {
        if (!isset($webhookData['signature'])) {
            return false;
        }
        
        $signature = $webhookData['signature'];
        
        // Remove signature from data before verification
        unset($webhookData['signature']);
        
        return self::verify($webhookData, $signature, $checksumKey);
    }
}