<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * Akun demo, semua dengan password "Password123".
     */
    public function run(): void
    {
        $branch = Branch::where('name', BranchSeeder::BRANCH_NAME)->firstOrFail();

        $accounts = [
            ['role' => 'owner', 'username' => 'owner', 'name' => 'Owner Ammar'],
            ['role' => 'cashier', 'username' => 'kasir1', 'name' => 'Kasir 1'],
            ['role' => 'kitchen', 'username' => 'koki1', 'name' => 'Koki 1'],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['username' => $account['username']],
                [
                    'branch_id' => $branch->id,
                    'role' => $account['role'],
                    'name' => $account['name'],
                    'password' => Hash::make('Password123'),
                ],
            );
        }
    }
}
