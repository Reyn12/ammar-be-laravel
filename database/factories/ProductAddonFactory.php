<?php

namespace Database\Factories;

use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAddon>
 */
class ProductAddonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => ProductAddonGroup::factory(),
            'name' => fake()->randomElement(['Dingin', 'Panas', 'Caramel', 'Vanilla']),
            'price' => fake()->randomElement([0, 3000, 5000]),
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
