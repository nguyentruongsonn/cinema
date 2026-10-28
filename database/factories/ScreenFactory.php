<?php

namespace Database\Factories;

use App\Models\Screen;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScreenFactory extends Factory
{
    protected $model = Screen::class;

    public function definition(): array
    {
        return [
            'theater_id' => 1,
            'name' => 'Screen ' . $this->faker->numberBetween(1, 10),
            'code' => 'SCR-' . strtoupper($this->faker->bothify('??##')),
            'capacity' => $this->faker->numberBetween(50, 200),
            'status' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}