<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

echo "=== TESTING RBAC PERMISSIONS SYSTEM ===\n\n";

// Test 1: Check all permissions exist
echo "TEST 1: Checking permissions in database...\n";
$totalPermissions = Permission::count();
echo "Total permissions: $totalPermissions\n";

if ($totalPermissions === 45) {
    echo "✅ PASS: All 45 permissions created\n";
} else {
    echo "❌ FAIL: Expected 45 permissions, got $totalPermissions\n";
}

// Show permissions by group
$groups = Permission::select('group')->distinct()->pluck('group');
echo "\nPermission groups:\n";
foreach ($groups as $group) {
    $count = Permission::where('group', $group)->count();
    echo "  - $group: $count permissions\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 2: Check role permissions
echo "TEST 2: Checking role permissions assignment...\n";
$roles = ['admin', 'manager', 'staff', 'customer'];
$expectedCounts = [
    'admin' => 45,
    'manager' => 30,
    'staff' => 8,
    'customer' => 8,
];

foreach ($roles as $roleSlug) {
    $role = Role::where('slug', $roleSlug)->first();
    if (!$role) {
        echo "❌ FAIL: Role '$roleSlug' not found\n";
        continue;
    }

    $count = $role->permissions()->count();
    $expected = $expectedCounts[$roleSlug];

    if ($count === $expected) {
        echo "✅ PASS: {$role->name} has $count permissions (expected: $expected)\n";
    } else {
        echo "❌ FAIL: {$role->name} has $count permissions (expected: $expected)\n";
    }
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 3: Check specific user permissions
echo "TEST 3: Testing user permissions...\n";

// Get/create a customer test user with role_user pivot
$customerRole = Role::where('slug', 'customer')->first();
$testUser = User::where('email', 'rbac_customer_test@example.com')->first();

if (!$testUser) {
    echo "⚠️  Creating customer test user...\n";
    $testUser = User::create([
        'name' => 'RBAC Customer Test',
        'email' => 'rbac_customer_test@example.com',
        'username' => 'rbac_customer_test',
        'password' => bcrypt('password'),
        'status' => 1,
    ]);
}

if (!$testUser->hasRole('customer')) {
    $testUser->roles()->syncWithoutDetaching([$customerRole->id]);
}

$testUser->load('roles');

echo "\nUser: {$testUser->name} (Roles: " . $testUser->roles->pluck('name')->implode(', ') . ")\n";
echo "User has " . $testUser->roles()->with('permissions')->get()->pluck('permissions')->flatten()->unique('id')->count() . " permissions\n\n";

// Test specific permissions
$testCases = [
    'book_tickets' => true,  // Customer should have this
    'view_own_orders' => true, // Customer should have this
    'create_orders' => true, // Customer should have this
    'view_all_orders' => false, // Customer should NOT have this
    'create_movies' => false, // Customer should NOT have this
    'manage_settings' => false, // Customer should NOT have this
];

foreach ($testCases as $permission => $shouldHave) {
    $hasPermission = $testUser->hasPermission($permission);

    if ($hasPermission === $shouldHave) {
        $status = $shouldHave ? '✅ PASS' : '✅ PASS';
        $message = $shouldHave ? 'has' : 'does NOT have';
        echo "$status: User $message '$permission' permission (as expected)\n";
    } else {
        $status = '❌ FAIL';
        $message = $hasPermission ? 'has' : 'does NOT have';
        $expected = $shouldHave ? 'should have' : 'should NOT have';
        echo "$status: User $message '$permission' permission (but $expected)\n";
    }
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 4: Check admin has all permissions
echo "TEST 4: Checking admin has all permissions...\n";
$adminRole = Role::where('slug', 'admin')->first();
$adminUser = User::whereHas('roles', function ($query) {
    $query->where('slug', 'admin');
})->first();

if (!$adminUser && $adminRole) {
    echo "⚠️  Creating admin test user...\n";
    $adminUser = User::create([
        'name' => 'RBAC Admin Test',
        'email' => 'rbac_admin_test@example.com',
        'username' => 'rbac_admin_test',
        'password' => bcrypt('password'),
        'status' => 1,
    ]);
    $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
}

if ($adminUser) {
    echo "Admin user: {$adminUser->name}\n";
    $adminPermCount = $adminUser->permissions()->count();
    $totalPerms = Permission::count();

    if ($adminPermCount === $totalPerms) {
        echo "✅ PASS: Admin has all $totalPerms permissions\n";
    } else {
        echo "❌ FAIL: Admin has $adminPermCount permissions (expected: $totalPerms)\n";
    }

    // Test a few random permissions
    $testPerms = ['view_movies', 'create_movies', 'manage_settings', 'view_dashboard'];
    echo "\nTesting specific admin permissions:\n";
    foreach ($testPerms as $perm) {
        if ($adminUser->hasPermission($perm)) {
            echo "  ✅ Admin has '$perm'\n";
        } else {
            echo "  ❌ Admin missing '$perm'\n";
        }
    }
} else {
    echo "⚠️  WARNING: No admin user found\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// Test 5: Show detailed role permissions
echo "TEST 5: Detailed role permissions breakdown...\n\n";

foreach ($roles as $roleSlug) {
    $role = Role::where('slug', $roleSlug)->first();
    if (!$role) continue;

    echo "{$role->name} Permissions:\n";
    $perms = $role->permissions()->orderBy('group')->get()->groupBy('group');

    foreach ($perms as $group => $groupPerms) {
        echo "  $group:\n";
        foreach ($groupPerms as $perm) {
            echo "    - {$perm->name} ({$perm->slug})\n";
        }
    }
    echo "\n";
}

echo str_repeat("=", 50) . "\n";
echo "TESTING COMPLETED!\n";
echo str_repeat("=", 50) . "\n";
