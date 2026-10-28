<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class VerifyPayOSWebhookSignature
{
    public function handle(Request $request, Closure $next): mixed
    {
        $providedSignature = $request->header('x-payos-signature');

        if (!$providedSignature) {
            return response()->json([
                'success' => false,
                'message' => 'Missing signature',
            ], 401);
        }

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

        $body = $request->getContent();

        $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);

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

        return $next($request);
    }
}
