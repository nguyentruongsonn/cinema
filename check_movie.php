<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$movie = App\Models\Movie::find(1);

if ($movie) {
    echo "Movie found!\n";
    echo "ID: {$movie->id}\n";
    echo "Title: {$movie->title}\n";
    echo "Status: " . var_export($movie->status, true) . "\n";
    echo "Status type: " . gettype($movie->status) . "\n";
    echo "\nFull movie data:\n";
    print_r($movie->toArray());
} else {
    echo "Movie not found!\n";
}
