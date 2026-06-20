<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\ProductAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemAddon>
 */
class OrderItemAddonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'addon_id' => ProductAddon::factory(),
            'price' => fake()->randomElement([0, 3000, 5000]),
        ];
    }
}
