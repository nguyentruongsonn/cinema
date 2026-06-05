<?php

namespace Database\Seeders;

use App\Models\SeatType;
use Illuminate\Database\Seeder;

class SeatTypeSeeder extends Seeder
{
    public function run(): void
    {
        $seatTypes = [
            ['name' => 'Standard', 'surcharge' => 0.00],
            ['name' => 'VIP', 'surcharge' => 50000.00],
            ['name' => 'Couple', 'surcharge' => 100000.00],
        ];

        foreach ($seatTypes as $seatType) {
            SeatType::updateOrCreate(
                ['name' => $seatType['name']],
                $seatType
            );
        }

        $this->command->info('Seat types seeded successfully!');
    }
}
