<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAddonGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAddonGroup>
 */
class ProductAddonGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Variasi Suhu', 'Syrup', 'Additional']),
            'is_required' => fake()->boolean(),
            'min_qty' => 1,
            'max_qty' => 1,
        ];
    }
}
