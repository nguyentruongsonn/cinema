<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovieFactory extends Factory
{
    protected $model = Movie::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'original_title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'duration' => fake()->numberBetween(80, 180),
            'release_date' => fake()->dateTimeBetween('-1 month', '+2 months'),
            'director' => fake()->name(),
            'cast' => implode(', ', fake()->words(5)),
            'age_rating' => fake()->randomElement(['P', 'C13', 'C16', 'C18']),
            'trailer_url' => 'https://youtube.com/watch?v=' . fake()->uuid(),
            'poster_url' => fake()->imageUrl(400, 600, 'movies'),
            'is_hot' => fake()->boolean(20),
            'is_hidden' => 0,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => 1,
        ]);
    }

    public function hot(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hot' => 1,
        ]);
    }
}
