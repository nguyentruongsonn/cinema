<?php

namespace Database\Seeders;

use App\Models\Screen;
use App\Models\Seat;
use App\Models\SeatType;
use App\Models\Theater;
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

        $seatTypes = SeatType::all()->keyBy('name');

        if ($seatTypes->isEmpty()) {
            $this->command->warn('No seat types found. Please run SeatTypeSeeder first.');
            return;
        }

        $standardType = $seatTypes->get('Standard') ?? $seatTypes->first();
        $vipType = $seatTypes->get('VIP') ?? $standardType;
        $coupleType = $seatTypes->get('Couple') ?? $vipType;
        $sweetboxType = $seatTypes->get('Sweetbox') ?? $coupleType;
        $premiumType = $seatTypes->get('Premium') ?? $vipType;
        $accessibleType = $seatTypes->get('Accessible') ?? $standardType;

        $screenLayouts = [
            [
                'code' => 'S1',
                'name' => 'Screen 1 - Standard',
                'capacity' => 120,
                'rows' => 10,
                'columns' => 12,
            ],
            [
                'code' => 'S2',
                'name' => 'Screen 2 - Premium',
                'capacity' => 96,
                'rows' => 8,
                'columns' => 12,
            ],
            [
                'code' => 'S3',
                'name' => 'Screen 3 - Couple',
                'capacity' => 88,
                'rows' => 8,
                'columns' => 11,
            ],
        ];

        $screensCreated = 0;
        $seatsCreated = 0;

        foreach ($theaters as $theater) {
            foreach ($screenLayouts as $layout) {
                $screen = Screen::updateOrCreate(
                    [
                        'theater_id' => $theater->id,
                        'code' => $layout['code'],
                    ],
                    [
                        'name' => $layout['name'],
                        'capacity' => $layout['capacity'],
                        'status' => 1,
                    ]
                );

                $screensCreated++;

                Seat::query()->where('screen_id', $screen->id)->delete();

                for ($rowIndex = 0; $rowIndex < $layout['rows']; $rowIndex++) {
                    $rowLabel = chr(65 + $rowIndex);

                    for ($columnIndex = 0; $columnIndex < $layout['columns']; $columnIndex++) {
                        $seatNumber = $columnIndex + 1;
                        $seatType = $this->resolveSeatType(
                            $rowIndex,
                            $columnIndex,
                            $layout['rows'],
                            $layout['columns'],
                            $standardType,
                            $vipType,
                            $coupleType,
                            $sweetboxType,
                            $premiumType,
                            $accessibleType
                        );

                        Seat::create([
                            'screen_id' => $screen->id,
                            'seat_type_id' => $seatType->id,
                            'row' => $rowLabel,
                            'number' => (string) $seatNumber,
                            'row_index' => $rowIndex,
                            'column_index' => $columnIndex,
                            'label' => $rowLabel . $seatNumber,
                            'status' => 1,
                        ]);

                        $seatsCreated++;
                    }
                }
            }
        }

        $this->command->info("Screens seeded successfully! Created/updated {$screensCreated} screens with {$seatsCreated} seats including Standard, VIP, Couple, Sweetbox, Premium, and Accessible.");
    }

    private function resolveSeatType(
        int $rowIndex,
        int $columnIndex,
        int $totalRows,
        int $totalColumns,
        SeatType $standardType,
        SeatType $vipType,
        SeatType $coupleType,
        SeatType $sweetboxType,
        SeatType $premiumType,
        SeatType $accessibleType
    ): SeatType {
        $lastRowIndex = $totalRows - 1;
        $secondLastRowIndex = $totalRows - 2;

        // Accessible seats: first row, aisle-edge seats.
        if ($rowIndex === 0 && in_array($columnIndex, [0, 1, $totalColumns - 2, $totalColumns - 1], true)) {
            return $accessibleType;
        }

        // Couple/Sweetbox seats: back rows, paired seating area.
        if ($rowIndex === $lastRowIndex) {
            return $coupleType;
        }

        if ($rowIndex === $secondLastRowIndex && $columnIndex >= 2 && $columnIndex <= $totalColumns - 3) {
            return $sweetboxType;
        }

        // Premium seats: center block in middle rows.
        if (
            $rowIndex >= 2 &&
            $rowIndex <= $totalRows - 4 &&
            $columnIndex >= 2 &&
            $columnIndex <= $totalColumns - 3
        ) {
            return $premiumType;
        }

        // VIP seats: remaining middle/back area.
        if ($rowIndex >= 2) {
            return $vipType;
        }

        return $standardType;
    }
}
