<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $products = [
            [
                'name' => 'Bắp rang bơ size M',
                'type' => 'popcorn',
                'price' => 45000,
                'stock' => 200,
                'image_url' => '/images/products/popcorn-m.jpg',
                'description' => 'Bắp rang bơ truyền thống size M.',
                'status' => 1,
            ],
            [
                'name' => 'Bắp rang bơ size L',
                'type' => 'popcorn',
                'price' => 55000,
                'stock' => 200,
                'image_url' => '/images/products/popcorn-l.jpg',
                'description' => 'Bắp rang bơ truyền thống size L.',
                'status' => 1,
            ],
            [
                'name' => 'Bắp phô mai size L',
                'type' => 'popcorn',
                'price' => 65000,
                'stock' => 150,
                'image_url' => '/images/products/popcorn-cheese-l.jpg',
                'description' => 'Bắp rang vị phô mai size L.',
                'status' => 1,
            ],
            [
                'name' => 'Coca Cola size M',
                'type' => 'drink',
                'price' => 30000,
                'stock' => 300,
                'image_url' => '/images/products/coca-m.jpg',
                'description' => 'Nước ngọt Coca Cola size M.',
                'status' => 1,
            ],
            [
                'name' => 'Coca Cola size L',
                'type' => 'drink',
                'price' => 40000,
                'stock' => 300,
                'image_url' => '/images/products/coca-l.jpg',
                'description' => 'Nước ngọt Coca Cola size L.',
                'status' => 1,
            ],
            [
                'name' => 'Sprite size M',
                'type' => 'drink',
                'price' => 30000,
                'stock' => 250,
                'image_url' => '/images/products/sprite-m.jpg',
                'description' => 'Nước ngọt Sprite size M.',
                'status' => 1,
            ],
            [
                'name' => 'Nước suối',
                'type' => 'drink',
                'price' => 20000,
                'stock' => 300,
                'image_url' => '/images/products/water.jpg',
                'description' => 'Nước suối đóng chai.',
                'status' => 1,
            ],
            [
                'name' => 'Combo Solo',
                'type' => 'combo',
                'price' => 75000,
                'stock' => 150,
                'image_url' => '/images/products/combo-solo.jpg',
                'description' => '01 bắp size M + 01 nước size M.',
                'status' => 1,
            ],
            [
                'name' => 'Combo Couple',
                'type' => 'combo',
                'price' => 129000,
                'stock' => 150,
                'image_url' => '/images/products/combo-couple.jpg',
                'description' => '01 bắp size L + 02 nước size M, phù hợp cho 2 người.',
                'status' => 1,
            ],
            [
                'name' => 'Combo Family',
                'type' => 'combo',
                'price' => 199000,
                'stock' => 100,
                'image_url' => '/images/products/combo-family.jpg',
                'description' => '02 bắp size L + 04 nước size M, phù hợp gia đình/nhóm bạn.',
                'status' => 1,
            ],
            [
                'name' => 'Combo VIP',
                'type' => 'combo',
                'price' => 249000,
                'stock' => 80,
                'image_url' => '/images/products/combo-vip.jpg',
                'description' => '02 bắp phô mai size L + 02 nước size L + snack.',
                'status' => 1,
            ],
            [
                'name' => 'Snack khoai tây',
                'type' => 'snack',
                'price' => 35000,
                'stock' => 180,
                'image_url' => '/images/products/potato-snack.jpg',
                'description' => 'Snack khoai tây ăn kèm khi xem phim.',
                'status' => 1,
            ],
        ];

        foreach ($products as $product) {
            DB::table('products')->updateOrInsert(
                ['name' => $product['name']],
                array_merge($product, [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ])
            );
        }

        $this->command->info('Products and combos seeded successfully!');
    }
}
