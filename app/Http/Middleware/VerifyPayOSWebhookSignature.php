<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Verifies PayOS webhook signature to prevent forgery attacks.
 *
 * PayOS includes a signature in the x-payos-signature header.
 * We calculate HMAC-SHA256 of the request body using the webhook secret
 * and compare it with the provided signature.
 */
class VerifyPayOSWebhookSignature
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Extract signature from header
        $providedSignature = $request->header('x-payos-signature');

        if (!$providedSignature) {
            return response()->json([
                'success' => false,
                'message' => 'Missing signature',
            ], 401);
        }

        // Get webhook secret from config
        $webhookSecret = config('services.payos.webhook_secret');

        if (!$webhookSecret) {
            Log::error('PayOS webhook secret not configured', [
                'error' => 'PAYOS_WEBHOOK_SECRET not set in .env',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Webhook not configured',
            ], 500);
        }

        // Get raw request body
        $body = $request->getContent();

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);

        // Compare using hash_equals to prevent timing attacks
        if (!hash_equals($expectedSignature, $providedSignature)) {
            Log::warning('Invalid PayOS webhook signature', [
                'provided' => substr($providedSignature, 0, 10) . '...',
                'expected' => substr($expectedSignature, 0, 10) . '...',
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 401);
        }

        // Signature is valid, continue to next middleware/controller
        return $next($request);
    }
}
