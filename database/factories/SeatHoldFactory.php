<?php

namespace Database\Factories;

use App\Models\SeatHold;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeatHoldFactory extends Factory
{
    protected $model = SeatHold::class;

    public function definition(): array
    {
        return [
            'showtime_id' => Showtime::factory(),
            'user_id' => User::factory(),
            'seat_ids' => [1, 2, 3],
            'held_until' => now()->addMinutes(10),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'held_until' => now()->subMinutes(5),
        ]);
    }

    public function withSeats(array $seatIds): static
    {
        return $this->state(fn (array $attributes) => [
            'seat_ids' => $seatIds,
        ]);
    }
}