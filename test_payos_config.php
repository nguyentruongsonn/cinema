<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST PAYOS CONFIGURATION ===\n\n";

try {
    // Check config values
    $clientId = config('services.payos.client_id');
    $apiKey = config('services.payos.api_key');
    $checksumKey = config('services.payos.checksum_key');
    $env = config('services.payos.env');

    echo "📋 Current PayOS Config:\n";
    echo "   Client ID: " . ($clientId ? (strlen($clientId) > 10 ? substr($clientId, 0, 10) . '...' : $clientId) : '❌ NOT SET') . "\n";
    echo "   API Key: " . ($apiKey ? (strlen($apiKey) > 10 ? substr($apiKey, 0, 10) . '...' : $apiKey) : '❌ NOT SET') . "\n";
    echo "   Checksum Key: " . ($checksumKey ? (strlen($checksumKey) > 10 ? substr($checksumKey, 0, 10) . '...' : $checksumKey) : '❌ NOT SET') . "\n";
    echo "   Environment: " . ($env ?: 'sandbox') . "\n";
    echo "   Return URL: " . config('services.payos.return_url') . "\n";
    echo "   Cancel URL: " . config('services.payos.cancel_url') . "\n\n";

    // Check if still using placeholders
    $placeholders = [
        'paste_your_client_id_here',
        'paste_your_api_key_here',
        'paste_your_checksum_key_here'
    ];

    $hasPlaceholder = false;
    foreach ($placeholders as $placeholder) {
        if ($clientId === $placeholder || $apiKey === $placeholder || $checksumKey === $placeholder) {
            $hasPlaceholder = true;
            break;
        }
    }

    if ($hasPlaceholder || !$clientId || !$apiKey || !$checksumKey) {
        echo "⚠️  WARNING: PayOS chưa được cấu hình đúng!\n";
        echo "\n📝 Cần làm:\n";
        echo "   1. Mở file .env\n";
        echo "   2. Tìm dòng PAYOS_CLIENT_ID, PAYOS_API_KEY, PAYOS_CHECKSUM_KEY (dòng 83-85)\n";
        echo "   3. Thay thế placeholder bằng keys thực từ PayOS Dashboard\n";
        echo "   4. Save file .env\n";
        echo "   5. Chạy lại: php test_payos_config.php\n\n";
        exit(1);
    }

    // Try to instantiate service
    echo "🔄 Testing PayOSService instantiation...\n";
    $payosService = app(App\Services\PayOS\PayOSService::class);
    echo "✅ PayOSService khởi tạo thành công!\n\n";

    echo "✅ PayOS đã được cấu hình đúng!\n";
    echo "\n📌 Lưu ý:\n";
    echo "   - Để test thực tế, cần tạo order và gọi createPaymentLink()\n";
    echo "   - PayOS sẽ throw exception nếu credentials không hợp lệ khi call API\n";
    echo "   - Hiện tại đang dùng môi trường: " . ($env ?: 'sandbox') . "\n\n";

    echo "🎯 PayOS Service sẵn sàng sử dụng!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
