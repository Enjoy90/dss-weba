<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PRD-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(3, true),
            'category' => fake()->randomElement(['Sepatu', 'Sandal', 'Safety']),
            'unit_price' => fake()->numberBetween(80, 400) * 1000,
            'minutes_per_unit' => fake()->numberBetween(20, 90),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
