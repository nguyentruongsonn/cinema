<?php

namespace Database\Seeders;

use App\Models\Subtitle;
use Illuminate\Database\Seeder;

class SubtitleSeeder extends Seeder
{
    public function run(): void
    {
        $subtitles = [
            ['name' => 'Phụ đề Tiếng Việt'],
            ['name' => 'English Subtitle'],
            ['name' => 'Không phụ đề'],
        ];

        foreach ($subtitles as $subtitle) {
            Subtitle::updateOrCreate(
                ['name' => $subtitle['name']],
                ['name' => $subtitle['name']]
            );
        }

        $this->command->info('Subtitles seeded successfully!');
    }
}
