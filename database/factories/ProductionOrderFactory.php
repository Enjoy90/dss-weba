<?php

namespace Database\Factories;

use App\Enums\LockType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(20, 500),
            'order_date' => today(),
            'deadline_date' => today()->addDays(fake()->numberBetween(7, 30)),
            'unit_price' => fake()->numberBetween(80, 400) * 1000,
            'status' => OrderStatus::Queued,
            'created_by' => User::factory()->manager(),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::InProgress,
            'lock_type' => LockType::Auto,
            'locked_at' => now(),
        ]);
    }

    public function enteredDaysAgo(int $days): static
    {
        return $this->state(fn (array $attributes) => ['order_date' => today()->subDays($days)]);
    }

    public function dueInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => ['deadline_date' => today()->addDays($days)]);
    }

    public function late(int $daysOver = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'order_date' => today()->subDays($daysOver + 10),
            'deadline_date' => today()->subDays($daysOver),
        ]);
    }
}
