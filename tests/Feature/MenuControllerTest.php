<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;

test('menu is grouped by category with addon groups', function () {
    $branch = Branch::factory()->create();
    $category = Category::factory()->create(['name' => 'Minuman']);
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $category->id,
        'name' => 'Es Teh Manis',
        'price' => 6000,
    ]);

    $variant = ProductAddonGroup::factory()->create([
        'product_id' => $product->id,
        'name' => 'Level Es',
        'is_required' => true,
        'min_qty' => 1,
        'max_qty' => 1,
    ]);
    ProductAddon::factory()->create(['group_id' => $variant->id, 'name' => 'Normal', 'price' => 0]);

    $addon = ProductAddonGroup::factory()->create([
        'product_id' => $product->id,
        'name' => 'Tambahan',
        'is_required' => false,
        'min_qty' => 0,
        'max_qty' => 3,
    ]);
    ProductAddon::factory()->create(['group_id' => $addon->id, 'name' => 'Extra Susu', 'price' => 3000]);

    $this->getJson(route('v1.branches.menu', $branch))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Minuman')
        ->assertJsonPath('data.0.products.0.name', 'Es Teh Manis')
        ->assertJsonPath('data.0.products.0.price', 6000)
        ->assertJsonCount(2, 'data.0.products.0.addon_groups')
        ->assertJsonPath('data.0.products.0.addon_groups.0.type', 'variant')
        ->assertJsonPath('data.0.products.0.addon_groups.1.type', 'addon')
        ->assertJsonPath('data.0.products.0.addon_groups.1.addons.0.price', 3000);
});

test('menu only includes products from the requested branch', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $category = Category::factory()->create();

    Product::factory()->create(['branch_id' => $branch->id, 'category_id' => $category->id, 'name' => 'Milik A']);
    Product::factory()->create(['branch_id' => $otherBranch->id, 'category_id' => $category->id, 'name' => 'Milik B']);

    $this->getJson(route('v1.branches.menu', $branch))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.0.products')
        ->assertJsonPath('data.0.products.0.name', 'Milik A');
});

test('menu search filters products and drops empty categories', function () {
    $branch = Branch::factory()->create();
    $drinks = Category::factory()->create(['name' => 'Minuman']);
    $foods = Category::factory()->create(['name' => 'Makanan']);

    Product::factory()->create(['branch_id' => $branch->id, 'category_id' => $drinks->id, 'name' => 'Es Teh']);
    Product::factory()->create(['branch_id' => $branch->id, 'category_id' => $foods->id, 'name' => 'Ayam Bakar']);

    $this->getJson(route('v1.branches.menu', ['branch' => $branch, 'q' => 'ayam']))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Makanan')
        ->assertJsonPath('data.0.products.0.name', 'Ayam Bakar');
});

test('unavailable products are still listed so the app can mark them sold out', function () {
    $branch = Branch::factory()->create();
    $category = Category::factory()->create();

    Product::factory()->unavailable()->create(['branch_id' => $branch->id, 'category_id' => $category->id]);

    $this->getJson(route('v1.branches.menu', $branch))
        ->assertSuccessful()
        ->assertJsonPath('data.0.products.0.is_available', false);
});
