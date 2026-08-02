<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DayRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('day_rules')->truncate();
        DB::table('day_rules')->insert([
            [
                'day_of_week' => 0, // Sunday
                'day_type' => 'weekend',
                'surcharge' => 10000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day_of_week' => 1, // Monday
                'day_type' => 'weekday',
                'surcharge' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day_of_week' => 2, // Tuesday
                'day_type' => 'happy_day',
                'surcharge' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day_of_week' => 3, // Wednesday
                'day_type' => 'weekday',
                'surcharge' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day_of_week' => 4, // Thursday
                'day_type' => 'weekday',
                'surcharge' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day_of_week' => 5, // Friday
                'day_type' => 'weekend',
                'surcharge' => 10000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day_of_week' => 6, // Saturday
                'day_type' => 'weekend',
                'surcharge' => 10000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
