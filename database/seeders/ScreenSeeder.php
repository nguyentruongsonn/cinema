<?php

namespace Database\Seeders;

use App\Models\Screen;
use App\Models\Theater;
use App\Models\Seat;
use App\Models\SeatType;
use Illuminate\Database\Seeder;

class ScreenSeeder extends Seeder
{
    public function run(): void
    {
        $theaters = Theater::all();

        if ($theaters->isEmpty()) {
            $this->command->warn('No theaters found. Please run TheaterSeeder first.');
            return;
        }

        $seatType = SeatType::where('name', 'Standard')->first();
        if (!$seatType) {
            $this->command->warn('No seat types found. Please run SeatTypeSeeder first.');
            return;
        }

        $screensCreated = 0;
        $seatsCreated = 0;

        foreach ($theaters as $theater) {
            // Create 3-5 screens per theater
            $screenCount = rand(3, 5);

            for ($i = 1; $i <= $screenCount; $i++) {
                $code = 'S' . $i;

                $screen = Screen::updateOrCreate(
                    [
                        'theater_id' => $theater->id,
                        'code' => $code,
                    ],
                    [
                        'name' => 'Screen ' . $i,
                        'capacity' => 100,
                        'status' => 1,
                    ]
                );

                $screensCreated++;

                // Create seats for this screen (10 rows x 10 columns)
                $existingSeats = Seat::where('screen_id', $screen->id)->count();

                if ($existingSeats == 0) {
                    for ($row = 0; $row < 10; $row++) {
                        for ($col = 0; $col < 10; $col++) {
                            Seat::create([
                                'screen_id' => $screen->id,
                                'seat_type_id' => $seatType->id,
                                'row' => chr(65 + $row), // A, B, C, ...
                                'number' => $col + 1,
                                'row_index' => $row,
                                'column_index' => $col,
                                'status' => 1,
                            ]);
                            $seatsCreated++;
                        }
                    }
                }
            }
        }

        $this->command->info("Screens seeded successfully! Created {$screensCreated} screens with {$seatsCreated} seats.");
    }
}
