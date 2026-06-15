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
            ],
            [
                'name' => 'CGV Aeon Mall',
            ],
            [
                'name' => 'CGV Landmark 81',
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['name' => $branch['name']],
                $branch
            );
        }

        $this->command->info('Branches seeded successfully!');
    }
}
