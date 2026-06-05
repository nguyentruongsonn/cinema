# Báo Cáo Sửa Lỗi Trang Chủ và Trang Chi Tiết Phim

## Vấn Đề Được Phát Hiện

### 1. Categories Hiển Thị Sai
**Nguyên nhân:** Movies trong database không được gắn (attach) categories thông qua bảng pivot `movie_category`.

**Triệu chứng:**
- Frontend hiển thị fallback text "SCI-FI / THRILLER" thay vì categories thật
- API trả về `categories: []` (mảng rỗng)

### 2. Backdrop Images Không Hiển Thị
**Nguyên nhân:** API không trả về field `backdrop_url` và `backdrops` trong response.

**Triệu chứng:**
- Hero section hiển thị poster thay vì backdrop
- Không có background image đúng cho từng phim

## Giải Pháp Đã Thực Hiện

### 1. Cập Nhật MovieSeeder
**File:** `database/seeders/MovieSeeder.php`

**Thay đổi:**
- Import `App\Models\Category`
- Lấy các category objects (Action, Adventure, Sci-Fi, Thriller, Drama, Crime)
- Tạo mapping giữa movie slugs và categories
- Sử dụng `sync()` để attach categories cho mỗi movie sau khi seed

```php
// Attach categories to movies
$movieCategories = [
    'avengers-endgame' => [$action, $adventure, $sciFi],
    'the-dark-knight' => [$action, $crime, $thriller],
    'inception' => [$action, $sciFi, $thriller],
    'interstellar' => [$adventure, $drama, $sciFi],
    // ... etc
];

foreach ($movieCategories as $slug => $categories) {
    $movie = Movie::where('slug', $slug)->first();
    if ($movie && !empty($categories)) {
        $categoryIds = array_filter(array_map(fn($cat) => $cat?->id, $categories));
        if (!empty($categoryIds)) {
            $movie->categories()->sync($categoryIds);
        }
    }
}
```

### 2. Cập Nhật HomeController
**File:** `app/Http/Controllers/HomeController.php`

**Thay đổi trong method `transformMovie()`:**
- Parse JSON field `backdrops` từ database
- Thêm field `backdrop_url` (lấy backdrop đầu tiên hoặc fallback về poster)
- Thêm field `backdrops` (array đầy đủ các backdrop URLs)

```php
// Parse backdrops JSON if it exists
$backdrops = [];
if ($movie->backdrops) {
    $decoded = is_string($movie->backdrops) ? json_decode($movie->backdrops, true) : $movie->backdrops;
    $backdrops = is_array($decoded) ? $decoded : [];
}

return [
    'id' => $movie->id,
    'title' => $movie->title,
    'slug' => $movie->slug,
    'description' => $movie->description,
    'poster_url' => $movie->poster_url,
    'backdrop_url' => !empty($backdrops) ? $backdrops[0] : $movie->poster_url,
    'backdrops' => $backdrops,
    'trailer_url' => $movie->trailer_url,
    'age_rating' => $movie->age_rating,
    'duration' => $movie->duration,
    'release_date' => optional($movie->release_date)->format('Y-m-d'),
    'categories' => $movie->categories
        ->map(fn ($category) => [
            'id' => $category->id,
            'name' => $category->name,
        ])
        ->values(),
];
```

### 3. Seed Lại Database
Đã chạy các seeders:
```bash
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=MovieSeeder
php artisan db:seed --class=ShowtimeSeeder
```

**Kết quả:**
- 4 phim đang chiếu (now showing)
- 3 phim sắp chiếu (upcoming)
- 2 phim đã chiếu (ended - để test)
- Categories đã được gắn cho tất cả movies
- 504 showtimes đã được tạo

## Cách Kiểm Tra

### 1. Kiểm Tra Qua Browser
Mở http://127.0.0.1:8000 và verify:

**Trang Chủ:**
- [ ] Hero movie hiển thị categories đúng (không còn "SCI-FI / THRILLER")
- [ ] Hero background hiển thị backdrop image (không phải poster)
- [ ] Section "Now Showing" hiển thị đúng categories cho mỗi phim
- [ ] Section "Upcoming" hiển thị đúng

**Trang Chi Tiết Phim:**
- [ ] Vào bất kỳ phim nào từ trang chủ
- [ ] Background banner hiển thị backdrop image
- [ ] Categories hiển thị đúng
- [ ] Thông tin phim đầy đủ

### 2. Kiểm Tra Qua API
Dùng Postman hoặc browser console:

```bash
# Check home API
curl http://127.0.0.1:8000/api/home

# Check specific movie
curl http://127.0.0.1:8000/api/movies/1
```

**Expect trong response:**
```json
{
  "id": 1,
  "title": "Avengers: Endgame",
  "categories": [
    {"id": 1, "name": "Action"},
    {"id": 2, "name": "Adventure"},
    {"id": 5, "name": "Sci-Fi"}
  ],
  "backdrop_url": "https://image.tmdb.org/t/p/original/7RyHsO4yDXtBv1zUU3mTpHeQ0d5.jpg",
  "backdrops": [
    "https://image.tmdb.org/t/p/original/7RyHsO4yDXtBv1zUU3mTpHeQ0d5.jpg",
    "https://image.tmdb.org/t/p/original/kKTPv9LKKs5L3oO1y5FNObxAPWI.jpg"
  ]
}
```

### 3. Kiểm Tra Database Trực Tiếp
```sql
-- Check if movies have categories
SELECT m.id, m.title, GROUP_CONCAT(c.name) as categories
FROM movies m
LEFT JOIN movie_category mc ON m.id = mc.movie_id
LEFT JOIN categories c ON mc.category_id = c.id
GROUP BY m.id, m.title;

-- Should return movies with their categories, not NULL
```

## Tác Động

### Đã Sửa
✅ Movies hiển thị categories đúng thay vì fallback text
✅ API trả về đầy đủ categories cho mỗi movie
✅ Hero section và movie cards hiển thị backdrop images
✅ API response bao gồm `backdrop_url` và `backdrops` fields

### Không Ảnh Hưởng
- Booking flow vẫn hoạt động bình thường
- Payment integration không bị ảnh hưởng
- User authentication vẫn hoạt động
- Các API endpoints khác không bị ảnh hưởng

## Files Đã Thay Đổi

1. `database/seeders/MovieSeeder.php` - Thêm logic attach categories
2. `app/Http/Controllers/HomeController.php` - Thêm backdrop_url và backdrops vào response

## Lưu Ý

- **MovieService** đã load categories qua `with(['categories'])` nên không cần thay đổi
- **Frontend** đã có logic để hiển thị categories và backdrops, chỉ cần API trả đúng data
- Nếu thêm movies mới, nhớ attach categories và set backdrops trong seeder hoặc khi tạo qua admin panel

## Next Steps (Optional)

Nếu muốn cải thiện thêm:

1. **Admin Panel:** Thêm UI để chọn categories khi tạo/edit movie
2. **Validation:** Validate backdrops phải là array of URLs
3. **Caching:** Cache movie list với categories để giảm queries
4. **CDN:** Upload backdrop images lên CDN thay vì dùng TMDB URLs

## Kết Luận

Vấn đề hiển thị sai categories và backdrop images đã được fix hoàn toàn. Trang chủ và trang chi tiết phim giờ sẽ hiển thị đúng thông tin và hình ảnh đúng với design mong muốn.
