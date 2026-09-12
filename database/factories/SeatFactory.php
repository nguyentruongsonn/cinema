<?php

namespace Database\Factories;

use App\Models\Screen;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeatFactory extends Factory
{
    protected $model = Seat::class;

    public function definition(): array
    {
        $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $row = $this->faker->randomElement($rows);
        $number = $this->faker->numberBetween(1, 20);

        return [
            'screen_id' => Screen::factory(),
            'seat_type_id' => 1,
            'row' => $row,
            'number' => $number,
            'row_index' => array_search($row, $rows) + 1,
            'column_index' => $number,
            'label' => $row . $number,
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