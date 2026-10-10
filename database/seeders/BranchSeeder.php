<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Table;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public const BRANCH_NAME = 'RM Ayam Bakar Ammar – Bandung Kota';

    private const TABLE_COUNT = 20;

    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['name' => self::BRANCH_NAME],
            [
                'address' => 'Jl. Asia Afrika No. 100, Braga, Kec. Sumur Bandung, Kota Bandung',
                'description' => 'Rumah makan ayam bakar dan goreng dengan bumbu khas, cocok untuk makan bersama keluarga.',
                'phone' => '081234567890',
                'image_url' => 'images/menu-placeholder.png',
            ],
        );

        foreach (range(1, 7) as $day) {
            BranchOpeningHour::firstOrCreate(
                ['branch_id' => $branch->id, 'day_of_week' => $day],
                ['open_time' => '00:00', 'close_time' => '23:59', 'is_closed' => false],
            );
        }

        foreach (range(1, self::TABLE_COUNT) as $number) {
            Table::firstOrCreate(['branch_id' => $branch->id, 'table_number' => $number]);
        }
    }
}
