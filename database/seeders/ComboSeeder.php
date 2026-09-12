<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComboSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $combos = [
            [
                'name' => 'Combo Solo',
                'price' => 75000,
                'image_url' => '/images/products/combo-solo.jpg',
                'description' => '01 bắp size M + 01 nước size M.',
                'status' => 1,
            ],
            [
                'name' => 'Combo Couple',
                'price' => 129000,
                'image_url' => '/images/products/combo-couple.jpg',
                'description' => '01 bắp size L + 02 nước size M, phù hợp cho 2 người.',
                'status' => 1,
            ],
            [
                'name' => 'Combo Family',
                'price' => 199000,
                'image_url' => '/images/products/combo-family.jpg',
                'description' => '02 bắp size L + 04 nước size M, phù hợp gia đình/nhóm bạn.',
                'status' => 1,
            ],
            [
                'name' => 'Combo VIP',
                'price' => 249000,
                'image_url' => '/images/products/combo-vip.jpg',
                'description' => '02 bắp phô mai size L + 02 nước size L + snack.',
                'status' => 1,
            ],
        ];

        foreach ($combos as $combo) {
            DB::table('combos')->updateOrInsert(
                ['name' => $combo['name']],
                array_merge($combo, [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ])
            );
        }

        $this->command->info('Combos seeded successfully!');
    }
}