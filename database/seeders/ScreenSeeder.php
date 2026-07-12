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

        $templates = \App\Models\SeatLayoutTemplate::all();
        $templateStandard = $templates->where('template_name', '2D Standard')->first();

        $screenLayouts = [
            [
                'code' => 'P01',
                'name' => 'Phòng 01',
                'capacity' => 120,
                'rows' => 10,
                'columns' => 12,
                'seat_layout_template_id' => $templateStandard?->id,
            ],
            [
                'code' => 'P02',
                'name' => 'Phòng 02',
                'capacity' => 120,
                'rows' => 10,
                'columns' => 12,
                'seat_layout_template_id' => $templateStandard?->id,
            ],
            [
                'code' => 'P03',
                'name' => 'Phòng 03',
                'capacity' => 120,
                'rows' => 10,
                'columns' => 12,
                'seat_layout_template_id' => $templateStandard?->id,
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
                        'seat_layout_template_id' => $layout['seat_layout_template_id'],
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
                            $coupleType
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

        $this->command->info("Screens seeded successfully! Created/updated {$screensCreated} screens with {$seatsCreated} seats including Standard, VIP, and Couple.");
    }

    private function resolveSeatType(
        int $rowIndex,
        int $columnIndex,
        int $totalRows,
        int $totalColumns,
        SeatType $standardType,
        SeatType $vipType,
        SeatType $coupleType
    ): SeatType {
        $lastRowIndex = $totalRows - 1;

        // Couple seats: back row
        if ($rowIndex === $lastRowIndex) {
            return $coupleType;
        }

        // VIP seats: middle/back area.
        if ($rowIndex >= 2) {
            return $vipType;
        }

        return $standardType;
    }
}
