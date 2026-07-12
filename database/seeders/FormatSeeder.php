<?php

namespace Database\Seeders;

use App\Models\Format;
use Illuminate\Database\Seeder;

class FormatSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            ['name' => 'IMAX', 'surcharge' => 50000],
            ['name' => '2D', 'surcharge' => 0],
            ['name' => '3D', 'surcharge' => 30000],
        ];

        foreach ($formats as $format) {
            Format::updateOrCreate(
                ['name' => $format['name']],
                [
                    'name' => $format['name'],
                    'surcharge' => $format['surcharge'],
                ]
            );
        }

        $this->command->info('Formats seeded successfully!');
    }
}
