<?php

namespace Database\Factories;

use App\Models\Criterion;
use App\Models\CriterionScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CriterionScale>
 */
class CriterionScaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'criterion_id' => Criterion::factory(),
            'score' => 1,
            'label' => null,
            'lower_bound' => null,
            'upper_bound' => null,
        ];
    }
}
