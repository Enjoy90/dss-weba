<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Criterion;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_order_id' => ProductionOrder::factory(),
            'criterion_id' => Criterion::factory(),
            'raw_value' => fake()->numberBetween(10, 90),
            'is_overridden' => false,
            'value_updated_at' => now(),
        ];
    }

    public function updatedDaysAgo(int $days): static
    {
        return $this->state(fn (array $attributes) => ['value_updated_at' => now()->subDays($days)]);
    }
}
