# Database Setup Guide - Cinema Booking System

## Schema Overview (CORRECTED)

### Database Structure

```
movies
├── showtimes (có format_id, sound_id, subtitle_id)
│   ├── format (IMAX, 4DX, 2D, 3D)
│   ├── sound (Dolby Atmos, DTS, Standard)
│   ├── subtitle (Vietsub, English, No subtitle)
│   └── screen
│       └── theater
└── movie_subtitle (pivot: movie many-to-many subtitle)
```

**Key Points:**
- **Screen không có format_id/sound_id** - Phòng chiếu chỉ là không gian vật lý
- **Showtime có format_id/sound_id/subtitle_id** - Mỗi suất chiếu xác định format/sound/subtitle
- **Movie có nhiều format/subtitle** - Qua các showtimes hoặc pivot table

## Tables Schema

### 1. formats
```sql
CREATE TABLE `formats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,           -- IMAX, 4DX, 2D, 3D
  `slug` varchar(255),
  `description` text DEFAULT NULL,
  `surcharge` decimal(15,2) DEFAULT 0.00, -- Phụ phí format
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
);
```

### 2. sounds
```sql
CREATE TABLE `sounds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,           -- Dolby Atmos, DTS, Standard
  `slug` varchar(255),
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
);
```

### 3. subtitles
```sql
CREATE TABLE `subtitles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,           -- Vietsub, English, No subtitle
  `slug` varchar(255),
  `language_code` varchar(10),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
);
```

### 4. screens (KHÔNG có format_id, sound_id)
```sql
CREATE TABLE `screens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `theater_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED,
  `code` varchar(255),
  `name` varchar(255) NOT NULL,           -- Screen 1, Screen 2, Phòng A
  `capacity` int(11) NOT NULL,
  `rows` int(11),
  `columns` int(11),
  `seat_matrix` json,
  `screen_type` varchar(50),
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
);
```

### 5. showtimes (CÓ format_id, sound_id, subtitle_id)
```sql
CREATE TABLE `showtimes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `movie_id` bigint(20) UNSIGNED NOT NULL,
  `screen_id` bigint(20) UNSIGNED NOT NULL,
  `format_id` bigint(20) UNSIGNED NOT NULL,    -- Format của suất chiếu này
  `sound_id` bigint(20) UNSIGNED NOT NULL,     -- Sound của suất chiếu này
  `subtitle_id` bigint(20) UNSIGNED NOT NULL,  -- Subtitle của suất chiếu này
  `price_rule_id` bigint(20) UNSIGNED,
  `scheduled_at` datetime NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `pricing_snapshot` json,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
);
```

### 6. movie_subtitle (Many-to-many)
```sql
CREATE TABLE `movie_subtitle` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `movie_id` bigint(20) UNSIGNED NOT NULL,
  `subtitle_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
);
```

## Seed Data Scripts

### Step 1: Seed Formats

```sql
INSERT INTO `formats` (`name`, `slug`, `description`, `surcharge`) VALUES
('IMAX', 'imax', 'Large Screen - Màn hình siêu lớn với âm thanh vòm', 50000.00),
('4DX', '4dx', 'Motion Seats - Ghế chuyển động với hiệu ứng đặc biệt', 80000.00),
('2D', '2d', 'Phim 2D thông thường', 0.00),
('3D', '3d', 'Phim 3D với kính đặc biệt', 30000.00),
('Standard', 'standard', 'Phòng chiếu tiêu chuẩn 2D/3D', 0.00);
```

### Step 2: Seed Sounds

```sql
INSERT INTO `sounds` (`name`, `slug`, `description`) VALUES
('Dolby Atmos', 'dolby-atmos', 'Công nghệ âm thanh vòm cao cấp'),
('DTS', 'dts', 'Âm thanh kỹ thuật số chất lượng cao'),
('Standard', 'standard', 'Âm thanh tiêu chuẩn');
```

### Step 3: Seed Subtitles

```sql
INSERT INTO `subtitles` (`name`, `slug`, `language_code`) VALUES
('Phụ đề Tiếng Việt', 'vietsub', 'vi'),
('English Subtitle', 'engsub', 'en'),
('Không phụ đề', 'no-subtitle', NULL);
```

### Step 4: Seed Theaters (nếu chưa có)

```sql
INSERT INTO `theaters` (`name`, `address`, `phone`, `status`) VALUES
('Cinema Premium Downtown', '123 Nguyen Hue, Q1, HCMC', '0281234567', 1),
('Cinema Premium Mall', '456 Le Lai, Q1, HCMC', '0281234568', 1);
```

### Step 5: Seed Screens (KHÔNG có format_id, sound_id)

```sql
-- Screens cho Theater 1
INSERT INTO `screens` (`theater_id`, `code`, `name`, `capacity`, `status`) VALUES
(1, 'S01', 'Phòng 1', 200, 1),
(1, 'S02', 'Phòng 2', 180, 1),
(1, 'S03', 'Phòng 3', 150, 1),
(1, 'S04', 'Phòng VIP', 100, 1);

-- Screens cho Theater 2
INSERT INTO `screens` (`theater_id`, `code`, `name`, `capacity`, `status`) VALUES
(2, 'A01', 'Screen A', 220, 1),
(2, 'A02', 'Screen B', 180, 1),
(2, 'A03', 'Screen C', 160, 1);
```

### Step 6: Seed Showtimes với Format/Sound/Subtitle

```sql
-- Giả sử:
-- movie_id = 1 (phim cần thêm suất chiếu)
-- format_id: 1=IMAX, 2=4DX, 3=2D, 4=3D
-- sound_id: 1=Dolby Atmos, 2=DTS, 3=Standard
-- subtitle_id: 1=Vietsub, 2=Engsub, 3=No subtitle

-- IMAX showtime (screen 1, Dolby Atmos, Vietsub)
INSERT INTO `showtimes` 
(`movie_id`, `screen_id`, `format_id`, `sound_id`, `subtitle_id`, `scheduled_at`, `price`, `status`) 
VALUES
(1, 1, 1, 1, 1, '2026-06-03 13:00:00', 150000.00, 1),
(1, 1, 1, 1, 1, '2026-06-03 16:30:00', 150000.00, 1),
(1, 1, 1, 1, 1, '2026-06-03 19:30:00', 180000.00, 1),
(1, 1, 1, 1, 1, '2026-06-03 22:00:00', 150000.00, 1);

-- 4DX showtime (screen 2, Dolby Atmos, Vietsub)
INSERT INTO `showtimes` 
(`movie_id`, `screen_id`, `format_id`, `sound_id`, `subtitle_id`, `scheduled_at`, `price`, `status`) 
VALUES
(1, 2, 2, 1, 1, '2026-06-03 13:00:00', 180000.00, 1),
(1, 2, 2, 1, 1, '2026-06-03 16:30:00', 180000.00, 1),
(1, 2, 2, 1, 1, '2026-06-03 19:30:00', 210000.00, 1),
(1, 2, 2, 1, 1, '2026-06-03 22:00:00', 180000.00, 1);

-- 2D showtime (screen 3, Standard sound, Vietsub)
INSERT INTO `showtimes` 
(`movie_id`, `screen_id`, `format_id`, `sound_id`, `subtitle_id`, `scheduled_at`, `price`, `status`) 
VALUES
(1, 3, 3, 3, 1, '2026-06-03 10:00:00', 100000.00, 1),
(1, 3, 3, 3, 1, '2026-06-03 12:30:00', 100000.00, 1),
(1, 3, 3, 3, 1, '2026-06-03 15:00:00', 100000.00, 1),
(1, 3, 3, 3, 1, '2026-06-03 17:30:00', 120000.00, 1),
(1, 3, 3, 3, 1, '2026-06-03 20:00:00', 120000.00, 1),
(1, 3, 3, 3, 1, '2026-06-03 22:30:00', 100000.00, 1);

-- 3D showtime (screen 4, DTS, Vietsub)
INSERT INTO `showtimes` 
(`movie_id`, `screen_id`, `format_id`, `sound_id`, `subtitle_id`, `scheduled_at`, `price`, `status`) 
VALUES
(1, 4, 4, 2, 1, '2026-06-03 13:00:00', 130000.00, 1),
(1, 4, 4, 2, 1, '2026-06-03 16:00:00', 130000.00, 1),
(1, 4, 4, 2, 1, '2026-06-03 19:00:00', 150000.00, 1),
(1, 4, 4, 2, 1, '2026-06-03 22:00:00', 130000.00, 1);
```

### Step 7: Link Movie với Subtitles (Optional)

```sql
-- Phim 1 hỗ trợ Vietsub và Engsub
INSERT INTO `movie_subtitle` (`movie_id`, `subtitle_id`) VALUES
(1, 1),  -- Vietsub
(1, 2);  -- Engsub
```

## Laravel Seeder (Recommended)

Create `database/seeders/MovieShowtimeSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Format;
use App\Models\Sound;
use App\Models\Subtitle;
use App\Models\Theater;
use App\Models\Screen;
use App\Models\Showtime;
use App\Models\Movie;
use Carbon\Carbon;

class MovieShowtimeSeeder extends Seeder
{
    public function run()
    {
        // 1. Seed Formats
        $formats = [
            ['name' => 'IMAX', 'slug' => 'imax', 'description' => 'Large Screen', 'surcharge' => 50000],
            ['name' => '4DX', 'slug' => '4dx', 'description' => 'Motion Seats', 'surcharge' => 80000],
            ['name' => '2D', 'slug' => '2d', 'description' => '2D Standard', 'surcharge' => 0],
            ['name' => '3D', 'slug' => '3d', 'description' => '3D with glasses', 'surcharge' => 30000],
        ];

        foreach ($formats as $format) {
            Format::firstOrCreate(['slug' => $format['slug']], $format);
        }

        // 2. Seed Sounds
        $sounds = [
            ['name' => 'Dolby Atmos', 'slug' => 'dolby-atmos'],
            ['name' => 'DTS', 'slug' => 'dts'],
            ['name' => 'Standard', 'slug' => 'standard'],
        ];

        foreach ($sounds as $sound) {
            Sound::firstOrCreate(['slug' => $sound['slug']], $sound);
        }

        // 3. Seed Subtitles
        $subtitles = [
            ['name' => 'Phụ đề Tiếng Việt', 'slug' => 'vietsub', 'language_code' => 'vi'],
            ['name' => 'English Subtitle', 'slug' => 'engsub', 'language_code' => 'en'],
            ['name' => 'Không phụ đề', 'slug' => 'no-subtitle', 'language_code' => null],
        ];

        foreach ($subtitles as $subtitle) {
            Subtitle::firstOrCreate(['slug' => $subtitle['slug']], $subtitle);
        }

        // 4. Create sample showtimes (giả sử đã có movie, theater, screen)
        $movie = Movie::first();
        $screen = Screen::first();
        
        if ($movie && $screen) {
            $format = Format::where('slug', 'imax')->first();
            $sound = Sound::where('slug', 'dolby-atmos')->first();
            $subtitle = Subtitle::where('slug', 'vietsub')->first();

            // Create showtimes for next 7 days
            for ($i = 0; $i < 7; $i++) {
                $date = Carbon::today()->addDays($i);
                
                foreach (['13:00', '16:30', '19:30', '22:00'] as $time) {
                    Showtime::create([
                        'movie_id' => $movie->id,
                        'screen_id' => $screen->id,
                        'format_id' => $format->id,
                        'sound_id' => $sound->id,
                        'subtitle_id' => $subtitle->id,
                        'scheduled_at' => $date->copy()->setTimeFromTimeString($time),
                        'price' => 150000,
                        'status' => 1,
                    ]);
                }
            }
        }
    }
}
```

Run seeder:
```bash
php artisan db:seed --class=MovieShowtimeSeeder
```

## Backend API (ShowtimeController)

```php
public function getMovieShowtimes($movieId)
{
    $showtimes = Showtime::with([
            'screen.theater',
            'format',
            'sound', 
            'subtitle'
        ])
        ->where('movie_id', $movieId)
        ->where('status', 1)
        ->where('scheduled_at', '>=', now())
        ->orderBy('scheduled_at')
        ->get()
        ->map(function ($showtime) {
            return [
                'id' => $showtime->id,
                'movie_id' => $showtime->movie_id,
                'format' => [
                    'id' => $showtime->format->id,
                    'name' => $showtime->format->name,
                    'description' => $showtime->format->description,
                    'surcharge' => $showtime->format->surcharge,
                ],
                'sound' => [
                    'id' => $showtime->sound->id,
                    'name' => $showtime->sound->name,
                ],
                'subtitle' => [
                    'id' => $showtime->subtitle->id,
                    'name' => $showtime->subtitle->name,
                ],
                'theater' => [
                    'id' => $showtime->screen->theater->id,
                    'name' => $showtime->screen->theater->name,
                ],
                'screen' => [
                    'id' => $showtime->screen->id,
                    'name' => $showtime->screen->name,
                ],
                'scheduled_at' => $showtime->scheduled_at->format('Y-m-d H:i:s'),
                'price' => $showtime->price,
                'total_price' => $showtime->price + $showtime->format->surcharge,
            ];
        });

    return response()->json([
        'success' => true,
        'data' => $showtimes
    ]);
}
```

## Quick Start

1. Chạy SQL INSERT statements qua phpMyAdmin
2. Verify: `SELECT * FROM formats;`
3. Verify: `SELECT * FROM showtimes WHERE movie_id = 1;`
4. Test API: `GET /api/movies/1/showtimes`
5. Frontend sẽ tự động group theo format và hiển thị

## Notes

- **Screen không chứa format/sound** - Phòng chiếu là không gian vật lý, có thể chiếu bất kỳ format nào tùy suất chiếu
- **Showtime xác định format/sound/subtitle** - Mỗi suất chiếu cụ thể có format/sound/subtitle riêng
- **Price calculation**: `total_price = showtime.price + format.surcharge`
- **Frontend đã sẵn sàng** - JavaScript sẽ tự động group showtimes theo format khi API trả về đúng structure
