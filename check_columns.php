<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $columns = DB::select('SHOW COLUMNS FROM users');

    echo "=== USERS TABLE COLUMNS ===\n";
    foreach ($columns as $column) {
        echo $column->Field . " | " . $column->Type . " | " . $column->Null . " | " . $column->Key . "\n";
    }

    echo "\n=== ROLES TABLE COLUMNS ===\n";
    $rolesColumns = DB::select('SHOW COLUMNS FROM roles');
    foreach ($rolesColumns as $column) {
        echo $column->Field . " | " . $column->Type . " | " . $column->Null . " | " . $column->Key . "\n";
    }

    echo "\n=== ROLES DATA ===\n";
    $roles = DB::table('roles')->get();
    foreach ($roles as $role) {
        echo "ID: {$role->id} | Name: {$role->name} | Slug: " . ($role->slug ?? 'NULL') . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
