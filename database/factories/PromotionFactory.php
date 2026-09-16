<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??????')),
            'name' => fake()->words(3, true),
            'discount_type' => fake()->randomElement(['percentage', 'fixed_amount']),
            'discount_value' => fake()->numberBetween(5, 50),
            'min_order_value' => fake()->numberBetween(50000, 200000),
            'max_discount_amount' => fake()->numberBetween(100000, 500000),
            'usage_limit' => fake()->numberBetween(50, 500),
            'usage_count' => 0,
            'start_date' => now()->subDays(7),
            'end_date' => now()->addDays(30),
            'status' => 1,
            'description' => fake()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => true,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subMonth(),
            'end_date' => now()->subDay(),
        ]);
    }

    public function percentage(int $value = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => 'percentage',
            'discount_value' => $value,
        ]);
    }

    public function fixed(int $value = 50000): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => 'fixed_amount',
            'discount_value' => $value,
        ]);
    }
}
