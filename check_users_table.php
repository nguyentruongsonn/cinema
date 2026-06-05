<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$columns = DB::select('DESCRIBE users');

echo "Users table columns:\n";
echo str_repeat('=', 50) . "\n";

foreach ($columns as $column) {
    if (in_array($column->Field, ['id', 'name', 'full_name', 'username', 'email', 'avatar_url', 'last_login_at', 'last_login_ip'])) {
        echo sprintf("%-20s %-20s %s\n", $column->Field, $column->Type, $column->Null);
    }
}

echo "\nChecking for specific columns:\n";
echo "- 'name' exists: " . (collect($columns)->contains('Field', 'name') ? 'YES' : 'NO') . "\n";
echo "- 'full_name' exists: " . (collect($columns)->contains('Field', 'full_name') ? 'YES' : 'NO') . "\n";
echo "- 'username' exists: " . (collect($columns)->contains('Field', 'username') ? 'YES' : 'NO') . "\n";
