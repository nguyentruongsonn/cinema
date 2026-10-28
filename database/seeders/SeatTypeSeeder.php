<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeatTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $seatTypes = [
            [
                'name' => 'Standard',
                'surcharge' => 0,
                'color' => '#6c757d',
            ],
            [
                'name' => 'VIP',
                'surcharge' => 10000,
                'color' => '#ffc107',
            ],
            [
                'name' => 'Couple',
                'surcharge' => 20000,
                'color' => '#e83e8c',
            ],
        ];

        foreach ($seatTypes as $seatType) {
            DB::table('seat_types')->updateOrInsert(
                ['name' => $seatType['name']],
                array_merge($seatType, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $this->command->info('Seat types seeded successfully!');
    }
}
