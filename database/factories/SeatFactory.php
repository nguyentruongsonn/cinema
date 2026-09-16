<?php

namespace Database\Factories;

use App\Models\Screen;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Seat>
 */
class SeatFactory extends Factory
{
    protected $model = Seat::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $row = $this->faker->randomElement($rows);
        $number = $this->faker->numberBetween(1, 20);

        return [
            'screen_id' => Screen::factory(),
            'seat_type_id' => 1, // Default seat type (tests can override)
            'row' => $row,
            'number' => $number,
            'row_index' => array_search($row, $rows) + 1,
            'column_index' => $number,
            'label' => $row . $number,
            'status' => true,
        ];
    }

    /**
     * Indicate that the seat is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}