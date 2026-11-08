<?php

namespace Database\Factories;

use App\Enums\CriterionDirection;
use App\Enums\ValueSource;
use App\Models\Criterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Criterion>
 */
class CriterionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'K'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->words(2, true),
            'unit' => 'tingkat',
            'direction' => CriterionDirection::HigherFirst,
            'value_source' => ValueSource::Manual,
            'importance' => 3,
            'is_active' => true,
        ];
    }

    public function importance(int $level): static
    {
        return $this->state(fn (array $attributes) => ['importance' => $level]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function lowerFirst(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => CriterionDirection::LowerFirst]);
    }
}
