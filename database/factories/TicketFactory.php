<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'showtime_id' => Showtime::factory(),
            'seat_id' => Seat::factory(),
            'ticket_code' => $this->generateUniqueCode(),
            'qr_code' => null,
            'status' => Ticket::STATUS_VALID,
            'checked_in_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Ticket::STATUS_USED,
            'checked_in_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Ticket::STATUS_CANCELLED,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Ticket::STATUS_REFUNDED,
        ]);
    }

    private function generateUniqueCode(): string
    {
        return 'TKT-' . strtoupper($this->faker->unique()->bothify('??########'));
    }
}