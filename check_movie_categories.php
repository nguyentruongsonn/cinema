<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Movie;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

echo "=== CHECKING MOVIE CATEGORIES IN DATABASE ===\n\n";

// Check if categories exist
echo "1. Categories in database:\n";
$categories = Category::all();
if ($categories->isEmpty()) {
    echo "   [ERROR] No categories found in database!\n";
    echo "   Run: php artisan db:seed --class=CategorySeeder\n\n";
} else {
    foreach ($categories as $cat) {
        echo "   - {$cat->id}: {$cat->name}\n";
    }
    echo "\n";
}

// Check movies and their categories
echo "2. Movies and their categories:\n";
$movies = Movie::with('categories')->get();

if ($movies->isEmpty()) {
    echo "   [ERROR] No movies found in database!\n";
    echo "   Run: php artisan db:seed --class=MovieSeeder\n\n";
} else {
    foreach ($movies as $movie) {
        echo "\n   Movie: {$movie->title} (ID: {$movie->id})\n";

        if ($movie->categories->isEmpty()) {
            echo "   [WARNING] No categories attached!\n";
        } else {
            echo "   Categories: " . $movie->categories->pluck('name')->join(', ') . "\n";
        }
    }
}

// Check pivot table directly
echo "\n3. Checking movie_category pivot table:\n";
$pivotCount = DB::table('movie_category')->count();
echo "   Total relations: {$pivotCount}\n";

if ($pivotCount === 0) {
    echo "   [ERROR] movie_category table is empty!\n";
    echo "   The MovieSeeder needs to attach categories.\n\n";
    echo "   Solution: Re-run MovieSeeder after ensuring it has category attachment logic.\n";
} else {
    $relations = DB::table('movie_category')
        ->join('movies', 'movie_category.movie_id', '=', 'movies.id')
        ->join('categories', 'movie_category.category_id', '=', 'categories.id')
        ->select('movies.title', 'categories.name')
        ->get();

    foreach ($relations as $rel) {
        echo "   - {$rel->title} → {$rel->name}\n";
    }
}

echo "\n=== DIAGNOSIS ===\n";
if ($pivotCount === 0) {
    echo "❌ Categories are NOT attached to movies\n";
    echo "   Fix: Run: php artisan db:seed --class=MovieSeeder\n";
} else {
    echo "✅ Categories ARE attached to movies ({$pivotCount} relations)\n";
    echo "   If frontend still shows fallback, check:\n";
    echo "   1. Is Laravel server running?\n";
    echo "   2. Clear cache: php artisan cache:clear\n";
    echo "   3. Check browser console for API errors\n";
}
