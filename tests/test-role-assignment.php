<?php

/**
 * Test Role Assignment After Registration
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Role Assignment Fix\n";
echo str_repeat("=", 70) . "\n\n";

try {
    // Check if 'user' role exists
    echo "1. Checking if 'user' role exists... ";
    $userRole = \App\Models\Role::where('slug', 'user')->first();
    if ($userRole) {
        echo "✅ FOUND (ID: {$userRole->id}, Name: {$userRole->name})\n";
    } else {
        echo "❌ NOT FOUND\n";
        echo "ERROR: Role with slug 'user' does not exist!\n";
        exit(1);
    }

    // Create a test user
    echo "\n2. Creating test user... ";
    $testEmail = 'test_' . time() . '@example.com';
    $testUser = \App\Models\User::create([
        'name' => 'Test User',
        'email' => $testEmail,
        'username' => 'test_' . time(),
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'status' => 1,
    ]);
    echo "✅ CREATED (ID: {$testUser->id})\n";

    // Manually call assignDefaultRole (simulating registration)
    echo "\n3. Assigning default role... ";
    $authService = new \App\Services\AuthService();
    // Use reflection to call private method
    $reflection = new ReflectionClass($authService);
    $method = $reflection->getMethod('assignDefaultRole');
    $method->setAccessible(true);
    $method->invoke($authService, $testUser);
    echo "✅ ASSIGNED\n";

    // Check if role was assigned
    echo "\n4. Verifying role assignment... ";
    $testUser->load('roles');
    if ($testUser->roles->count() > 0) {
        echo "✅ SUCCESS\n";
        echo "   User has " . $testUser->roles->count() . " role(s):\n";
        foreach ($testUser->roles as $role) {
            echo "   - {$role->name} (slug: {$role->slug})\n";
        }
    } else {
        echo "❌ FAILED\n";
        echo "   User has NO roles assigned!\n";
        exit(1);
    }

    // Clean up
    echo "\n5. Cleaning up test user... ";
    $testUser->roles()->detach();
    $testUser->delete();
    echo "✅ DELETED\n";

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "🎉 ALL TESTS PASSED!\n";
    echo "Role assignment is working correctly.\n";
    exit(0);

} catch (\Exception $e) {
    echo "\n\n❌ TEST FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
