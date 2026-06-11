<?php

namespace Database\Factories;

use App\Models\SeatHold;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SeatHold>
 */
class SeatHoldFactory extends Factory
{
    protected $model = SeatHold::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'showtime_id' => Showtime::factory(),
            'user_id' => User::factory(),
            'seat_ids' => [1, 2, 3], // Default seat IDs (tests can override)
            'held_until' => now()->addMinutes(10), // Default 10-minute hold
        ];
    }

    /**
     * Indicate that the hold is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'held_until' => now()->subMinutes(5),
        ]);
    }

    /**
     * Set custom seat IDs.
     */
    public function withSeats(array $seatIds): static
    {
        return $this->state(fn (array $attributes) => [
            'seat_ids' => $seatIds,
        ]);
    }
}