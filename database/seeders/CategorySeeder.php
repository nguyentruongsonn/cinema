<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Action', 'description' => 'Action movies'],
            ['name' => 'Adventure', 'description' => 'Adventure movies'],
            ['name' => 'Comedy', 'description' => 'Comedy movies'],
            ['name' => 'Drama', 'description' => 'Drama movies'],
            ['name' => 'Horror', 'description' => 'Horror movies'],
            ['name' => 'Thriller', 'description' => 'Thriller movies'],
            ['name' => 'Sci-Fi', 'description' => 'Science fiction movies'],
            ['name' => 'Fantasy', 'description' => 'Fantasy movies'],
            ['name' => 'Romance', 'description' => 'Romance movies'],
            ['name' => 'Animation', 'description' => 'Animated movies'],
            ['name' => 'Documentary', 'description' => 'Documentary films'],
            ['name' => 'Crime', 'description' => 'Crime movies'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'slug' => Str::slug($category['name']),
                    'description' => $category['description'],
                ]
            );
        }

        $this->command->info('Categories seeded successfully!');
    }
}
