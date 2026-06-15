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
            ],
            [
                'name' => 'CGV Aeon Mall',
                'address' => '30 Bo Bao Tan Thang, Son Ky Ward, Tan Phu District',
                'phone' => '1900 6017',
                'email' => 'aeon@cgv.vn',
                'status' => 1,
            ],
            [
                'name' => 'CGV Landmark 81',
                'address' => '720A Dien Bien Phu, Ward 22, Binh Thanh District',
                'phone' => '1900 6017',
                'email' => 'landmark81@cgv.vn',
                'status' => 1,
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
