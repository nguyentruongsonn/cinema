<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'showtime_id' => Showtime::factory(),
            'seat_id' => Seat::factory(),
            'ticket_code' => $this->generateUniqueCode(),
            'qr_code' => null, // Can be set later if needed
            'status' => Ticket::STATUS_VALID,
            'checked_in_at' => null,
        ];
    }

    /**
     * Indicate that the ticket has been used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Ticket::STATUS_USED,
            'checked_in_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Indicate that the ticket has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Ticket::STATUS_CANCELLED,
        ]);
    }

    /**
     * Indicate that the ticket has been refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Ticket::STATUS_REFUNDED,
        ]);
    }

    /**
     * Generate a unique ticket code.
     */
    private function generateUniqueCode(): string
    {
        return 'TKT-' . strtoupper($this->faker->unique()->bothify('??########'));
    }
}