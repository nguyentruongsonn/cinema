<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Screen;
use App\Models\Showtime;
use Illuminate\Database\Seeder;

class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        // Only create showtimes for movies currently showing (release_date <= now AND (end_date IS NULL OR end_date >= now))
        $movies = Movie::where('status', 1)
            ->where('is_hidden', 0)
            ->where('release_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->get();

        $screens = Screen::all();

        if ($movies->isEmpty()) {
            $this->command->warn('No now-showing movies found. Please run MovieSeeder first.');
            return;
        }

        if ($screens->isEmpty()) {
            $this->command->warn('No screens found. Please run ScreenSeeder first.');
            return;
        }

        // Get format IDs for variety
        $format2D = 3;   // Standard 2D
        $format3D = 4;   // 3D
        $formatIMAX = 1; // IMAX
        $format4DX = 2;  // 4DX

        $now = now();
        $showtimesCreated = 0;

        foreach ($movies as $movie) {
            $screenIndex = 0;
            foreach ($screens->take(3) as $screen) {
                // Create showtimes for next 7 days
                for ($day = 0; $day < 7; $day++) {
                    $date = $now->copy()->addDays($day);

                    // Determine format based on screen index for variety
                    // Screen 1: mostly 2D/3D, Screen 2: 3D/IMAX, Screen 3: IMAX/4DX
                    $morningFormat = $screenIndex == 0 ? $format2D : ($screenIndex == 1 ? $format3D : $format2D);
                    $lateFormat = $screenIndex == 0 ? $format3D : ($screenIndex == 1 ? $formatIMAX : $format3D);
                    $premiumFormat = $screenIndex == 2 ? $format4DX : $formatIMAX;

                    // Morning showtime (9:00) - Budget price, mostly 2D
                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $date->copy()->setTime(9, 0),
                        ],
                        [
                            'format_id' => $morningFormat,
                            'price' => 80000,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    // Late morning showtime (11:00) - Standard price, mixed formats
                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $date->copy()->setTime(11, 0),
                        ],
                        [
                            'format_id' => $screenIndex % 2 == 0 ? $format2D : $format3D,
                            'price' => 90000,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    // Afternoon showtime (14:00) - 3D/IMAX mix
                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $date->copy()->setTime(14, 0),
                        ],
                        [
                            'format_id' => $lateFormat,
                            'price' => 100000,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    // Evening showtime (17:00) - Premium formats
                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $date->copy()->setTime(17, 0),
                        ],
                        [
                            'format_id' => $lateFormat,
                            'price' => 110000,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    // Prime time showtime (19:30) - IMAX/4DX premium
                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $date->copy()->setTime(19, 30),
                        ],
                        [
                            'format_id' => $premiumFormat,
                            'price' => 130000,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    // Late night showtime (22:00) - Mixed premium
                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $date->copy()->setTime(22, 0),
                        ],
                        [
                            'format_id' => $screenIndex == 1 ? $formatIMAX : $format3D,
                            'price' => 120000,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;
                }
                $screenIndex++;
            }
        }

        $this->command->info("Showtimes seeded successfully! Created {$showtimesCreated} showtimes.");
        $this->command->info('- Movies: ' . $movies->count());
        $this->command->info('- Screens per movie: ' . min(3, $screens->count()));
        $this->command->info('- Days: 7');
        $this->command->info('- Showtimes per day: 6');
    }
}
