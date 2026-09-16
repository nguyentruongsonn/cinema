<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('holidays')->truncate();
        DB::table('holidays')->insert([
            [
                'name' => 'Tết Dương lịch',
                'date' => '01-01',
                'year' => null,
                'surcharge' => 20000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Giải phóng miền Nam',
                'date' => '30-04',
                'year' => null,
                'surcharge' => 20000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Quốc tế Lao động',
                'date' => '01-05',
                'year' => null,
                'surcharge' => 20000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Quốc khánh',
                'date' => '02-09',
                'year' => null,
                'surcharge' => 20000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
