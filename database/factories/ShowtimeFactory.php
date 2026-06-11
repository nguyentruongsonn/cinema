<?php

namespace Database\Factories;

use App\Models\Movie;
use App\Models\Screen;
use App\Models\Showtime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Showtime>
 */
class ShowtimeFactory extends Factory
{
    protected $model = Showtime::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movie_id' => Movie::factory(),
            'screen_id' => Screen::factory(),
            'scheduled_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
            'price' => $this->faker->randomFloat(2, 50, 200),
            'status' => true,
        ];
    }

    /**
     * Indicate that the showtime is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    /**
     * Indicate that the showtime is in the past.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}