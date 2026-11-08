<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PLG-'.fake()->unique()->numerify('####'),
            'name' => 'PT '.fake()->unique()->company(),
            'contact' => fake()->phoneNumber(),
            'urgency_level' => fake()->numberBetween(1, 5),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function urgency(int $level): static
    {
        return $this->state(fn (array $attributes) => ['urgency_level' => $level]);
    }
}
