<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VersionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $versionTypes = [
            [
                'name' => 'Phụ đề',
                'slug' => 'phu-de',
                'description' => 'Phim có phụ đề tiếng Việt',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lồng tiếng',
                'slug' => 'long-tieng',
                'description' => 'Phim được lồng tiếng tiếng Việt',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Thuyết minh',
                'slug' => 'thuyet-minh',
                'description' => 'Phim có thuyết minh tiếng Việt',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Nguyên bản',
                'slug' => 'nguyen-ban',
                'description' => 'Phim nguyên bản không phụ đề',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('version_types')->insert($versionTypes);

        $this->command->info('Version types seeded successfully!');
    }
}
