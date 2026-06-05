<?php

namespace Database\Seeders;

use App\Models\Sound;
use Illuminate\Database\Seeder;

class SoundSeeder extends Seeder
{
    public function run(): void
    {
        $sounds = [
            ['name' => 'Dolby Atmos'],
            ['name' => 'Dolby Digital'],
            ['name' => 'Âm thanh thường'],
        ];

        foreach ($sounds as $sound) {
            Sound::updateOrCreate(
                ['name' => $sound['name']],
                ['name' => $sound['name']]
            );
        }

        $this->command->info('Sounds seeded successfully!');
    }
}
