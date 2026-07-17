<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Theater;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Theater>
 */
class TheaterFactory extends Factory
{
    protected $model = Theater::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => $this->faker->company() . ' Cinema',
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'status' => 1,
            'pricing_profile' => null,
        ];
    }

    /**
     * Indicate that the theater is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }

    /**
     * Indicate that the theater belongs to a specific branch.
     */
    public function forBranch(int $branchId): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branchId,
        ]);
    }
}
