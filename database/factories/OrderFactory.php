<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => null,
            'table_id' => null,
            'branch_id' => Branch::factory(),
            'order_type' => fake()->randomElement(['dine_in', 'takeaway']),
            'status' => 'pending',
            'payment_method' => null,
            'payment_status' => 'unpaid',
            'total_amount' => 0,
        ];
    }

    public function dineIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_type' => 'dine_in',
            'table_id' => Table::factory(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => fake()->randomElement(['qris', 'cash']),
            'total_amount' => fake()->numberBetween(10000, 500000),
        ]);
    }
}
