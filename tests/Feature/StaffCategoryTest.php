<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->owner = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'owner']);
    $this->kasir = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);
    Sanctum::actingAs($this->owner);
});

test('owner can add and rename a category', function () {
    $this->postJson(route('v1.staff.categories.store'), ['name' => 'Dessert'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Dessert')
        ->assertJsonPath('data.product_count', 0);

    $category = Category::firstWhere('name', 'Dessert');

    $this->putJson(route('v1.staff.categories.update', $category), ['name' => 'Penutup'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Penutup');

    // Nama yang sama dengan dirinya sendiri tetap boleh disimpan.
    $this->putJson(route('v1.staff.categories.update', $category), ['name' => 'Penutup'])->assertSuccessful();
});

test('category names must be unique among active categories', function () {
    Category::factory()->create(['name' => 'Minuman']);

    $this->postJson(route('v1.staff.categories.store'), ['name' => 'Minuman'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    Category::firstWhere('name', 'Minuman')->delete();

    $this->postJson(route('v1.staff.categories.store'), ['name' => 'Minuman'])->assertCreated();
});

test('a category with menu cannot be deleted but an empty one can', function () {
    $category = Category::factory()->create(['name' => 'Snack']);
    Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $category->id]);

    $this->deleteJson(route('v1.staff.categories.destroy', $category))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category');

    Product::query()->delete();

    $this->deleteJson(route('v1.staff.categories.destroy', $category))->assertSuccessful();
    expect(Category::count())->toBe(0)
        ->and(Category::withTrashed()->count())->toBe(1);
});

test('archived menu keeps its category name in old orders after the category is deleted', function () {
    $category = Category::factory()->create(['name' => 'Snack']);
    $product = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $category->id]);
    $order = Order::factory()->create(['branch_id' => $this->branch->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name]);

    $product->delete();
    $this->deleteJson(route('v1.staff.categories.destroy', $category))->assertSuccessful();

    $this->getJson(route('v1.staff.orders.show', $order))
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.category_name', 'Snack');
});

test('category list includes how many menu each category has', function () {
    $category = Category::factory()->create(['name' => 'Makanan']);
    Product::factory()->count(2)->create(['branch_id' => $this->branch->id, 'category_id' => $category->id]);

    $this->getJson(route('v1.staff.categories'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.product_count', 2);
});

test('cashier cannot manage categories', function () {
    Sanctum::actingAs($this->kasir);
    $category = Category::factory()->create();

    $this->postJson(route('v1.staff.categories.store'), ['name' => 'Baru'])->assertForbidden();
    $this->putJson(route('v1.staff.categories.update', $category), ['name' => 'Baru'])->assertForbidden();
    $this->deleteJson(route('v1.staff.categories.destroy', $category))->assertForbidden();
});
