<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Config;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Config>
 */
class ConfigFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'key' => fake()->randomElement(['store_name', 'tax', 'service_charge', 'wifi_password']),
            'value' => fake()->word(),
        ];
    }
}
