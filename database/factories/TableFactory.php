<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'table_number' => fake()->numberBetween(1, 50),
            'qr_code_url' => null,
        ];
    }
}
