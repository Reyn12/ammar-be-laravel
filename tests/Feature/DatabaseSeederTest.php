<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAddonGroup;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('database seeder fills store, staff, tables and menu', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Branch::count())->toBe(1)
        ->and(Table::count())->toBe(20)
        ->and(User::count())->toBe(3)
        ->and(Category::count())->toBe(6)
        ->and(Product::count())->toBe(96)
        ->and(Product::whereNull('image_url')->count())->toBe(0);
});

test('database seeder can run twice without duplicating data', function () {
    $this->seed(DatabaseSeeder::class);
    $groups = ProductAddonGroup::count();

    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe(96)
        ->and(Table::count())->toBe(20)
        ->and(ProductAddonGroup::count())->toBe($groups);
});

test('seeded menu exposes variants and addons through the api', function () {
    $this->seed(DatabaseSeeder::class);
    $branch = Branch::first();

    $this->getJson(route('v1.branches.menu', ['branch' => $branch, 'q' => 'Ayam Bakar / Goreng 1 Potong']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.products.0.price', 17000)
        ->assertJsonCount(3, 'data.0.products.0.addon_groups')
        ->assertJsonPath('data.0.products.0.addon_groups.0.type', 'variant')
        ->assertJsonPath('data.0.products.0.addon_groups.2.type', 'addon');

    $this->getJson(route('v1.branches.menu', ['branch' => $branch, 'q' => 'Air Mineral']))
        ->assertSuccessful()
        ->assertJsonCount(0, 'data.0.products.0.addon_groups');
});

test('seeded packages list their contents and ask for chicken type', function () {
    $this->seed(DatabaseSeeder::class);
    $branch = Branch::first();

    $this->getJson(route('v1.branches.menu', ['branch' => $branch, 'q' => 'Paket Hemat 3']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.products.0.price', 28000)
        ->assertJsonPath('data.0.products.0.components.0.name', 'Ayam Bakar / Goreng 1 Potong')
        ->assertJsonCount(5, 'data.0.products.0.components')
        ->assertJsonPath('data.0.products.0.addon_groups.0.name', 'Tipe Ayam');
});

test('seeder renames old package names without creating duplicates', function () {
    $this->seed(DatabaseSeeder::class);
    Product::where('name', 'Paket Hemat 1')->update(['name' => 'Paket 24K (Tanpa Minuman)', 'price' => 24000]);

    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe(96)
        ->and(Product::where('name', 'Paket Hemat 1')->value('price'))->toBe(25000)
        ->and(Product::where('name', 'Paket Hemat 1')->first()->components()->count())->toBe(4);
});
