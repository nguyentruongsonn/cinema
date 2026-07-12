<?php

namespace Database\Seeders;

use App\Models\Theater;
use Illuminate\Database\Seeder;

class TheaterSeeder extends Seeder
{
    public function run(): void
    {
        $theaters = [
            [
                'name' => 'CGV Vincom Center',
                'address' => '72 Le Thanh Ton Street, Ben Nghe Ward, District 1',
                'phone' => '1900 6017',
                'email' => 'vincom@cgv.vn',
                'status' => 1,
                'pricing_profile' => [
                    'base_price' => 80000,
                    'weekend_surcharge' => 15000,
                    'holiday_surcharge' => 25000,
                    'happy_day_price' => 60000,
                    'student_discount' => 15000,
                    'beta_ten_discount' => -10000,
                ],
            ],
            [
                'name' => 'CGV Aeon Mall',
                'address' => '30 Bo Bao Tan Thang, Son Ky Ward, Tan Phu District',
                'phone' => '1900 6017',
                'email' => 'aeon@cgv.vn',
                'status' => 1,
                'pricing_profile' => [
                    'base_price' => 70000,
                    'weekend_surcharge' => 10000,
                    'holiday_surcharge' => 20000,
                    'happy_day_price' => 50000,
                    'student_discount' => 10000,
                    'beta_ten_discount' => -10000,
                ],
            ],
            [
                'name' => 'CGV Landmark 81',
                'address' => '720A Dien Bien Phu, Ward 22, Binh Thanh District',
                'phone' => '1900 6017',
                'email' => 'landmark81@cgv.vn',
                'status' => 1,
                'pricing_profile' => [
                    'base_price' => 90000,
                    'weekend_surcharge' => 20000,
                    'holiday_surcharge' => 30000,
                    'happy_day_price' => 70000,
                    'student_discount' => 20000,
                    'beta_ten_discount' => -10000,
                ],
            ],
        ];

        foreach ($theaters as $theater) {
            $branch = \App\Models\Branch::where('name', $theater['name'])->first();
            if ($branch) {
                $theater['branch_id'] = $branch->id;
            }
            Theater::updateOrCreate(
                ['name' => $theater['name']],
                $theater
            );
        }

        $this->command->info('Theaters seeded successfully!');
    }
}
