<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TimeSlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('time_slots')->truncate();
        DB::table('time_slots')->insert([
            [
                'name' => 'Thường',
                'start_time' => '08:00:00',
                'end_time' => '17:59:59',
                'surcharge' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tối',
                'start_time' => '18:00:00',
                'end_time' => '22:00:00',
                'surcharge' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Khuya',
                'start_time' => '22:00:01',
                'end_time' => '07:59:59',
                'surcharge' => 10000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
