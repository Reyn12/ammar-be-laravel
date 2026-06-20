<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('-1 week', 'now');

        return [
            'user_id' => User::factory(),
            'start_time' => $startTime,
            'end_time' => null,
            'status' => 'active',
            'starting_cash' => fake()->numberBetween(100000, 500000),
            'actual_cash' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(function (array $attributes) {
            $endTime = fake()->dateTimeBetween($attributes['start_time'], 'now');

            return [
                'end_time' => $endTime,
                'status' => 'closed',
                'actual_cash' => fake()->numberBetween(100000, 1000000),
            ];
        });
    }
}
