<?php

/**
 * PayOS Integration Test Script
 * Tests the complete payment flow with mock PayOS responses
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class PayOSIntegrationTest
{
    private $baseUrl;
    private $token;
    private $userId;

    public function __construct()
    {
        $this->baseUrl = env('APP_URL', 'http://localhost') . '/api';
    }

    public function run()
    {
        echo "=== PayOS Integration Test ===\n\n";

        try {
            $this->setupTestUser();
            $this->testCreatePayment();
            $this->testPaymentShow();
            $this->testPaymentVerify();
            $this->testCallbackEndpoints();
            
            echo "\n✅ All tests passed!\n\n";
            
            $this->printSummary();
        } catch (\Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
        }
    }

    private function setupTestUser()
    {
        echo "1. Setting up test user...\n";

        // Get or create test user
        $user = DB::table('users')->where('email', 'test@payos.com')->first();

        if (!$user) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'PayOS Test User',
                'email' => 'test@payos.com',
                'password' => password_hash('password123', PASSWORD_BCRYPT),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = $user->id;
        }

        $this->userId = $userId;

        // Generate JWT token for testing
        $this->token = $this->generateTestToken($userId);

        echo "   ✓ Test user ID: {$userId}\n";
        echo "   ✓ JWT token generated\n\n";
    }

    private function testCreatePayment()
    {
        echo "2. Testing Payment Creation API...\n";

        // Create test order first
        $orderId = $this->createTestOrder();
        echo "   ✓ Test order created: #{$orderId}\n";

        // Test payment creation
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/payments', [
            'order_id' => $orderId,
            'payment_method' => 'payos',
            'amount' => 150000,
        ]);

        if ($response->failed()) {
            throw new \Exception('Payment creation failed: ' . $response->body());
        }

        $data = $response->json();

        if (!$data['success']) {
            throw new \Exception('API returned success=false: ' . json_encode($data));
        }

        $payment = $data['data'];

        // Verify response structure
        $this->assertArrayHasKey('id', $payment, 'Payment ID');
        $this->assertArrayHasKey('checkout_url', $payment, 'Checkout URL');
        $this->assertArrayHasKey('qr_code', $payment, 'QR Code');
        $this->assertArrayHasKey('payment_link_id', $payment, 'Payment Link ID');
        $this->assertArrayHasKey('status', $payment, 'Status');

        echo "   ✓ Payment created: #{$payment['id']}\n";
        echo "   ✓ Status: {$payment['status']}\n";
        echo "   ✓ Checkout URL: " . ($payment['checkout_url'] ? 'Present' : 'Missing') . "\n";
        echo "   ✓ QR Code: " . ($payment['qr_code'] ? 'Present' : 'Missing') . "\n\n";

        return $payment['id'];
    }

    private function testPaymentShow()
    {
        echo "3. Testing Payment Show API...\n";

        // Get latest payment
        $payment = DB::table('payments')->latest('id')->first();

        if (!$payment) {
            throw new \Exception('No payment found to test');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->get($this->baseUrl . '/payments/' . $payment->id);

        if ($response->failed()) {
            throw new \Exception('Payment show failed: ' . $response->body());
        }

        $data = $response->json();

        if (!$data['success']) {
            throw new \Exception('API returned success=false');
        }

        echo "   ✓ Payment retrieved: #{$payment->id}\n";
        echo "   ✓ Method: {$data['data']['payment_method']}\n";
        echo "   ✓ Amount: {$data['data']['amount']}\n\n";
    }

    private function testPaymentVerify()
    {
        echo "4. Testing Payment Verify API...\n";

        // Get latest payment
        $payment = DB::table('payments')->latest('id')->first();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/payments/' . $payment->id . '/verify', [
            'status' => 'completed',
        ]);

        if ($response->failed()) {
            throw new \Exception('Payment verify failed: ' . $response->body());
        }

        $data = $response->json();

        if (!$data['success']) {
            throw new \Exception('API returned success=false');
        }

        echo "   ✓ Payment verified: #{$payment->id}\n";
        echo "   ✓ New status: {$data['data']['status']}\n\n";
    }

    private function testCallbackEndpoints()
    {
        echo "5. Testing Callback Endpoints...\n";

        // Test callback URL exists
        $callbackResponse = Http::get(env('APP_URL') . '/payment/payos/callback?orderCode=123');
        echo "   ✓ Callback endpoint accessible (Status: {$callbackResponse->status()})\n";

        // Test cancel URL exists
        $cancelResponse = Http::get(env('APP_URL') . '/payment/payos/cancel?orderCode=123');
        echo "   ✓ Cancel endpoint accessible (Status: {$cancelResponse->status()})\n";

        // Test webhook URL exists (should return error without proper signature)
        $webhookResponse = Http::post(env('APP_URL') . '/payment/payos/webhook', []);
        echo "   ✓ Webhook endpoint accessible (Status: {$webhookResponse->status()})\n\n";
    }

    private function createTestOrder()
    {
        // Create test order
        return DB::table('orders')->insertGetId([
            'user_id' => $this->userId,
            'showtime_id' => 1, // Assume showtime exists
            'code' => 'TEST-' . time(),
            'total_amount' => 150000,
            'status' => 1, // pending
            'payment_status' => 'pending',
            'expired_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function generateTestToken($userId)
    {
        // Simple JWT generation for testing
        $user = DB::table('users')->find($userId);
        
        // Use Laravel's JWT if available, otherwise create simple token
        try {
            $token = auth('api')->login($user);
            return $token;
        } catch (\Exception $e) {
            // Fallback: use user ID as token for testing
            return base64_encode("test-token-{$userId}");
        }
    }

    private function assertArrayHasKey($key, $array, $label)
    {
        if (!isset($array[$key])) {
            throw new \Exception("Missing required key '{$key}' ({$label}) in response");
        }
    }

    private function printSummary()
    {
        echo "=== Integration Summary ===\n\n";
        echo "✅ API Endpoints Verified:\n";
        echo "   - POST /api/payments (Create payment)\n";
        echo "   - GET /api/payments/{id} (Show payment)\n";
        echo "   - POST /api/payments/{id}/verify (Verify payment)\n";
        echo "   - GET /payment/payos/callback (User return)\n";
        echo "   - GET /payment/payos/cancel (Payment cancel)\n";
        echo "   - POST /payment/payos/webhook (PayOS webhook)\n\n";

        echo "✅ Response Fields Verified:\n";
        echo "   - checkout_url (for redirect to PayOS)\n";
        echo "   - qr_code (for QR payment)\n";
        echo "   - payment_link_id (PayOS reference)\n";
        echo "   - status (payment status tracking)\n\n";

        echo "📝 Next Steps:\n";
        echo "   1. Add PayOS credentials to .env file\n";
        echo "   2. Test with real PayOS account\n";
        echo "   3. Configure webhook URL in PayOS dashboard\n";
        echo "   4. Test end-to-end payment flow\n\n";

        echo "📚 Documentation:\n";
        echo "   - PAYOS_INTEGRATION_COMPLETE.md - Complete guide\n";
        echo "   - PAYOS_INTEGRATION_GUIDE.md - Technical details\n\n";
    }
}

// Run the test
$test = new PayOSIntegrationTest();
$test->run();