<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "SEAT TYPES:\n";
echo json_encode(\App\Models\SeatType::all(), JSON_PRETTY_PRINT);
echo "\n\nFORMATS:\n";
echo json_encode(\App\Models\Format::all(), JSON_PRETTY_PRINT);
echo "\n\nPRICE RULES:\n";
echo json_encode(\App\Models\PriceRule::all(), JSON_PRETTY_PRINT);
