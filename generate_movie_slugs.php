<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Movie;
use Illuminate\Support\Str;

echo "🔄 Generating slugs for existing movies...\n\n";

$movies = Movie::whereNull('slug')->orWhere('slug', '')->get();

if ($movies->isEmpty()) {
    echo "✅ All movies already have slugs!\n";
    exit(0);
}

echo "Found {$movies->count()} movies without slugs.\n\n";

$updated = 0;
foreach ($movies as $movie) {
    $slug = Str::slug($movie->title);
    $originalSlug = $slug;
    $counter = 1;

    // Ensure uniqueness
    while (Movie::where('slug', $slug)->where('id', '!=', $movie->id)->exists()) {
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }

    $movie->slug = $slug;
    $movie->save();

    echo "✓ {$movie->title} → {$slug}\n";
    $updated++;
}

echo "\n✅ Generated slugs for {$updated} movies!\n";
echo "🎬 URL structure: /movies/{slug}\n";
