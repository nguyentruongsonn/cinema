<?php

namespace Database\Factories;

use App\Models\Screen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Screen>
 */
class ScreenFactory extends Factory
{
    protected $model = Screen::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'theater_id' => 1, // Default theater ID (tests can override)
            'name' => 'Screen ' . $this->faker->numberBetween(1, 10),
            'code' => 'SCR-' . strtoupper($this->faker->bothify('??##')),
            'capacity' => $this->faker->numberBetween(50, 200),
            'status' => true,
        ];
    }

    /**
     * Indicate that the screen is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}