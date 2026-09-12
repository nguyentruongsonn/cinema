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
        $movies = Movie::where('status', 1)
            ->where('is_hidden', 0)
            ->where('release_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->get();

        $screens = Screen::all()->values();

        if ($movies->isEmpty()) {
            $this->command->warn('No now-showing movies found. Please run MovieSeeder first.');
            return;
        }

        if ($screens->isEmpty()) {
            $this->command->warn('No screens found. Please run ScreenSeeder first.');
            return;
        }

        $format2D = \App\Models\Format::where('name', '2D')->value('id') ?? 2;
        $format3D = \App\Models\Format::where('name', '3D')->value('id') ?? 3;
        $formatIMAX = \App\Models\Format::where('name', 'IMAX')->value('id') ?? 1;

        $versionSubtitle = 1; // Phụ đề
        $versionDubbed = 2;   // Lồng tiếng

        $now = now();
        $showtimesCreated = 0;

        $screensPerMovie = max(1, min(3, intdiv($screens->count(), max(1, $movies->count()))));

        foreach ($movies->values() as $movieIndex => $movie) {
            for ($screenIndex = 0; $screenIndex < $screensPerMovie; $screenIndex++) {
                $screenOffset = ($movieIndex * $screensPerMovie) + $screenIndex;
                $screen = $screens[$screenOffset % $screens->count()];
                $timeOffsetMinutes = intdiv($screenOffset, $screens->count()) * 10;

                for ($day = 0; $day < 7; $day++) {
                    $date = $now->copy()->addDays($day);
                    $scheduledAt = fn (int $hour, int $minute = 0) => $date->copy()
                        ->setTime($hour, $minute)
                        ->addMinutes($timeOffsetMinutes);

                    $morningFormat = $screenIndex == 0 ? $format2D : ($screenIndex == 1 ? $format3D : $format2D);
                    $lateFormat = $screenIndex == 0 ? $format3D : ($screenIndex == 1 ? $formatIMAX : $format3D);
                    $premiumFormat = $formatIMAX;

                    $morningVersion = $day % 3 == 0 ? $versionDubbed : $versionSubtitle;

                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $scheduledAt(9),
                        ],
                        [
                            'format_id' => $morningFormat,
                            'version_type_id' => $morningVersion,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $scheduledAt(11),
                        ],
                        [
                            'format_id' => $screenIndex % 2 == 0 ? $format2D : $format3D,
                            'version_type_id' => $versionSubtitle,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $scheduledAt(14),
                        ],
                        [
                            'format_id' => $lateFormat,
                            'version_type_id' => $versionSubtitle,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $scheduledAt(17),
                        ],
                        [
                            'format_id' => $lateFormat,
                            'version_type_id' => $versionSubtitle,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $scheduledAt(19, 30),
                        ],
                        [
                            'format_id' => $premiumFormat,
                            'version_type_id' => $versionSubtitle,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;

                    Showtime::updateOrCreate(
                        [
                            'movie_id' => $movie->id,
                            'screen_id' => $screen->id,
                            'scheduled_at' => $scheduledAt(22),
                        ],
                        [
                            'format_id' => $screenIndex == 1 ? $formatIMAX : $format3D,
                            'version_type_id' => $versionSubtitle,
                            'status' => 1,
                        ]
                    );
                    $showtimesCreated++;
                }
            }
        }

        $this->command->info("Showtimes seeded successfully! Created {$showtimesCreated} showtimes.");
        $this->command->info('- Movies: ' . $movies->count());
        $this->command->info('- Screens per movie: ' . $screensPerMovie);
        $this->command->info('- Days: 7');
        $this->command->info('- Showtimes per day: 6');
    }
}
