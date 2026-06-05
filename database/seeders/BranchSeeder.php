<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'CGV Vincom Center',
                'code' => 'VINCOM',
                'description' => '72 Le Thanh Ton Street, Ben Nghe Ward, District 1, Ho Chi Minh City',
            ],
            [
                'name' => 'CGV Aeon Mall',
                'code' => 'AEON',
                'description' => '30 Bo Bao Tan Thang, Son Ky Ward, Tan Phu District, Ho Chi Minh City',
            ],
            [
                'name' => 'CGV Landmark 81',
                'code' => 'LANDMARK81',
                'description' => '720A Dien Bien Phu, Ward 22, Binh Thanh District, Ho Chi Minh City',
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                $branch
            );
        }

        $this->command->info('Branches seeded successfully!');
    }
}
