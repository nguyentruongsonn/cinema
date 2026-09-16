<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Showtime;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'code' => 'ORD-' . fake()->unique()->numerify('######'),
            'gateway_order_code' => fake()->unique()->numberBetween(100000, 999999),
            'user_id' => User::factory(),
            'showtime_id' => Showtime::factory(),
            'total_amount' => fake()->numberBetween(100000, 500000),
            'status' => Order::STATUS_PENDING,
            'payment_provider' => 'payos',
            'payment_status' => 'pending',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CONFIRMED,
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
