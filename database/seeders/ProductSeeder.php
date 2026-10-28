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
            // Foods
            [
                'name' => 'Bắp rang bơ size M',
                'type' => 'food',
                'price' => 45000,
                'stock' => 200,
                'image_url' => '/images/products/popcorn-m.jpg',
                'description' => 'Bắp rang bơ truyền thống size M.',
                'status' => 1,
            ],
            [
                'name' => 'Bắp rang bơ size L',
                'type' => 'food',
                'price' => 55000,
                'stock' => 200,
                'image_url' => '/images/products/popcorn-l.jpg',
                'description' => 'Bắp rang bơ truyền thống size L.',
                'status' => 1,
            ],
            [
                'name' => 'Bắp phô mai size L',
                'type' => 'food',
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
                'name' => 'Snack khoai tây',
                'type' => 'food',
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

        $this->command->info('Products seeded successfully!');
    }
}
