<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Category;
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
            'branch_id' => Branch::factory(),
            'category_id' => Category::factory(),
            'image_url' => fake()->optional()->imageUrl(640, 480, 'food'),
            'name' => fake()->words(3, true),
            'price' => fake()->numberBetween(5000, 100000),
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
