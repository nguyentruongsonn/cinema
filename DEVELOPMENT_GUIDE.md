# 📖 Cinema - Hướng Dẫn Phát Triển

## 🎯 Quy Trình Phát Triển Feature Mới

### 1. Lập Kế Hoạch
- Xác định yêu cầu feature
- Thiết kế database schema (nếu cần)
- Lập danh sách API endpoints
- Xác định business logic

### 2. Database Layer
```bash
# Tạo migration mới
php artisan make:migration create_table_name

# Chỉnh sửa migration file
# database/migrations/YYYY_MM_DD_HHMMSS_create_table_name.php

# Chạy migration
php artisan migrate

# Rollback nếu cần
php artisan migrate:rollback
```

### 3. Model Layer
```bash
# Tạo model mới
php artisan make:model ModelName

# Tạo model với migration
php artisan make:model ModelName -m

# Tạo model với controller
php artisan make:model ModelName -c
```

**Model Best Practices:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movie extends Model
{
    // Fillable attributes
    protected $fillable = ['title', 'description', 'genre', 'rating'];

    // Hidden attributes
    protected $hidden = ['created_at', 'updated_at'];

    // Casts
    protected $casts = [
        'rating' => 'float',
        'release_date' => 'date',
    ];

    // Relationships
    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getFullTitleAttribute()
    {
        return "{$this->title} ({$this->genre})";
    }
}
```

### 4. Controller Layer
```bash
# Tạo controller mới
php artisan make:controller MovieController --api

# Tạo controller với model
php artisan make:controller MovieController --api --model=Movie
```

**Controller Best Practices:**
```php
<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    use ApiResponse;

    // List all movies
    public function index()
    {
        $movies = Movie::paginate(20);
        return $this->success($movies, 'Movies retrieved successfully');
    }

    // Get single movie
    public function show(Movie $movie)
    {
        return $this->success($movie, 'Movie retrieved successfully');
    }

    // Create movie (admin only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'genre' => 'required|string',
            'rating' => 'required|numeric|min:0|max:10',
            'duration' => 'required|integer|min:1',
            'release_date' => 'required|date',
        ]);

        $movie = Movie::create($validated);
        return $this->success($movie, 'Movie created successfully', 201);
    }

    // Update movie (admin only)
    public function update(Request $request, Movie $movie)
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'string',
            'genre' => 'string',
            'rating' => 'numeric|min:0|max:10',
            'duration' => 'integer|min:1',
            'release_date' => 'date',
        ]);

        $movie->update($validated);
        return $this->success($movie, 'Movie updated successfully');
    }

    // Delete movie (admin only)
    public function destroy(Movie $movie)
    {
        $movie->delete();
        return $this->success(null, 'Movie deleted successfully');
    }
}
```

### 5. Routes
```php
// routes/api.php

// Public routes
Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{movie}', [MovieController::class, 'show']);

// Protected routes (require JWT)
Route::middleware('auth:api')->group(function () {
    // Admin routes
    Route::middleware('admin')->group(function () {
        Route::post('/admin/movies', [MovieController::class, 'store']);
        Route::put('/admin/movies/{movie}', [MovieController::class, 'update']);
        Route::delete('/admin/movies/{movie}', [MovieController::class, 'destroy']);
    });
});
```

### 6. Testing
```bash
# Run tests
php artisan test

# Run specific test
php artisan test tests/Feature/MovieTest.php

# Test with coverage
php artisan test --coverage
```

**Test Example:**
```php
<?php

namespace Tests\Feature;

use App\Models\Movie;
use Tests\TestCase;

class MovieTest extends TestCase
{
    public function test_can_get_movies()
    {
        Movie::factory()->count(5)->create();
        
        $response = $this->getJson('/api/movies');
        
        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data');
    }

    public function test_can_create_movie()
    {
        $data = [
            'title' => 'Test Movie',
            'description' => 'Test Description',
            'genre' => 'Action',
            'rating' => 8.5,
            'duration' => 120,
            'release_date' => '2026-05-29',
        ];

        $response = $this->postJson('/api/admin/movies', $data);
        
        $response->assertStatus(201)
                 ->assertJsonPath('data.title', 'Test Movie');
    }
}
```

## 🔧 Công Cụ & Lệnh Hữu Ích

### Artisan Commands
```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Refresh database
php artisan migrate:refresh

# Seed database
php artisan db:seed

# Clear cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Tinker (interactive shell)
php artisan tinker

# Generate API documentation
php artisan scribe:generate
```

### Composer Commands
```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Require new package
composer require vendor/package

# Remove package
composer remove vendor/package

# Dump autoloader
composer dump-autoload
```

## 📋 Code Standards

### PSR-12 Compliance
```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // 4 spaces indentation
    public function index()
    {
        // Code here
    }

    // Method names in camelCase
    public function getUserById($id)
    {
        // Code here
    }

    // Class names in PascalCase
    // File names match class names
}
```

### Naming Conventions
| Item | Convention | Example |
|------|-----------|---------|
| Classes | PascalCase | `MovieController` |
| Methods | camelCase | `getMovieById()` |
| Variables | camelCase | `$movieId` |
| Constants | UPPER_SNAKE_CASE | `MAX_RETRIES` |
| Database Tables | snake_case (plural) | `movies`, `user_orders` |
| Database Columns | snake_case | `created_at`, `user_id` |
| Routes | kebab-case | `/api/user-orders` |

## 🧪 Debugging Tips

### Using Tinker
```bash
php artisan tinker

# Query database
>>> $movies = App\Models\Movie::all();
>>> $movies->count();

# Create record
>>> $movie = App\Models\Movie::create(['title' => 'Test']);

# Update record
>>> $movie->update(['title' => 'Updated']);

# Delete record
>>> $movie->delete();
```

### Logging
```php
// In controller or model
use Illuminate\Support\Facades\Log;

Log::info('User logged in', ['user_id' => $user->id]);
Log::error('Payment failed', ['order_id' => $order->id]);
Log::debug('Query executed', ['query' => $sql]);

// View logs
tail -f storage/logs/laravel.log
```

### Debugging with dd()
```php
// Dump and die
dd($variable);

// Dump without die
dump($variable);

// Multiple dumps
dd($var1, $var2, $var3);
```

## 📚 Useful Resources

### Laravel Documentation
- https://laravel.com/docs/11.x
- https://laravel.com/docs/11.x/eloquent
- https://laravel.com/docs/11.x/validation

### JWT Authentication
- https://github.com/tymondesigns/jwt-auth
- https://jwt.io

### Bootstrap 5
- https://getbootstrap.com/docs/5.0/

### MySQL
- https://dev.mysql.com/doc/

## 🚀 Performance Tips

### Database Optimization
```php
// Use eager loading to prevent N+1 queries
$movies = Movie::with('showtimes', 'theaters')->get();

// Use select to limit columns
$movies = Movie::select('id', 'title', 'rating')->get();

// Use whereIn for multiple IDs
$movies = Movie::whereIn('id', [1, 2, 3])->get();

// Use chunk for large datasets
Movie::chunk(100, function ($movies) {
    foreach ($movies as $movie) {
        // Process movie
    }
});
```

### Query Optimization
```php
// Use indexes on frequently queried columns
// In migration:
$table->index('email');
$table->index(['user_id', 'created_at']);

// Use database transactions
DB::transaction(function () {
    $order = Order::create($data);
    Payment::create(['order_id' => $order->id]);
});
```

### Caching
```php
use Illuminate\Support\Facades\Cache;

// Cache for 1 hour
$movies = Cache::remember('movies', 3600, function () {
    return Movie::all();
});

// Cache forever
$config = Cache::rememberForever('app_config', function () {
    return Config::all();
});

// Clear cache
Cache::forget('movies');
Cache::flush();
```

## 🐛 Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| "Class not found" | Run `composer dump-autoload` |
| "SQLSTATE error" | Check database connection in .env |
| "Token expired" | Refresh token or re-login |
| "Method not allowed" | Check HTTP method in route |
| "Validation failed" | Check request validation rules |

---

**Phiên bản**: 1.0.0  
**Cập nhật**: 2026-05-29
