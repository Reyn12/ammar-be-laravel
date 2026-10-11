<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use App\Models\ProductComponent;
use App\Models\Table;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->owner = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'owner']);
    $this->category = Category::factory()->create(['name' => 'Paket']);

    $make = fn (string $name, int $price, array $extra = []) => Product::factory()->create([
        'branch_id' => $this->branch->id, 'category_id' => $this->category->id, 'name' => $name, 'price' => $price,
    ] + $extra);

    $this->ayam = $make('Ayam Goreng', 17000);
    $this->nasi = $make('Nasi Putih', 5000);
    $this->teh = $make('Es Teh', 5000);

    $this->makePackage = fn (array $override = []) => $this->postJson(route('v1.staff.products.store'), array_merge([
        'category_id' => $this->category->id,
        'name' => 'Paket Baru',
        'price' => 25000,
        'components' => [
            ['component_id' => $this->ayam->id, 'qty' => 1, 'receives_options' => true],
            ['component_id' => $this->nasi->id, 'qty' => 1],
        ],
    ], $override));

    Sanctum::actingAs($this->owner);
});

test('owner creates a package with contents and the response lists them', function () {
    ($this->makePackage)()
        ->assertCreated()
        ->assertJsonCount(2, 'data.components')
        ->assertJsonPath('data.components.0.name', 'Ayam Goreng')
        ->assertJsonPath('data.components.0.price', 17000)
        ->assertJsonPath('data.components.0.receives_options', true)
        ->assertJsonPath('data.components.1.receives_options', false);

    expect(Product::firstWhere('name', 'Paket Baru')->components()->count())->toBe(2);
});

test('package contents are replaced when edited and cleared with an empty list', function () {
    $id = ($this->makePackage)()->json('data.id');

    $this->putJson(route('v1.staff.products.update', $id), [
        'price' => 27000,
        'components' => [
            ['component_id' => $this->ayam->id, 'qty' => 2, 'receives_options' => true],
            ['component_id' => $this->teh->id],
        ],
    ])->assertSuccessful()->assertJsonCount(2, 'data.components');

    $components = Product::find($id)->components()->get();
    expect($components->pluck('component_id')->all())->toBe([$this->ayam->id, $this->teh->id])
        ->and($components->first()->qty)->toBe(2);

    // Tanpa key components = isi tidak disentuh; array kosong = bukan paket lagi.
    $this->putJson(route('v1.staff.products.update', $id), ['name' => 'Paket Ganti'])->assertSuccessful();
    expect(Product::find($id)->components()->count())->toBe(2);

    $this->putJson(route('v1.staff.products.update', $id), ['components' => []])->assertSuccessful();
    expect(Product::find($id)->components()->count())->toBe(0);
});

test('package contents must be regular menu from the same branch', function () {
    $package = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id]);
    ProductComponent::create(['product_id' => $package->id, 'component_id' => $this->nasi->id]);
    $other = Product::factory()->create(['category_id' => $this->category->id, 'branch_id' => Branch::factory()->create()->id]);

    // paket tidak boleh jadi isi paket lain
    ($this->makePackage)(['components' => [['component_id' => $package->id]]])
        ->assertUnprocessable()->assertJsonValidationErrors('components.0.component_id');

    // menu cabang lain / tidak ada
    ($this->makePackage)(['components' => [['component_id' => $other->id]]])
        ->assertUnprocessable()->assertJsonValidationErrors('components.0.component_id');
    ($this->makePackage)(['components' => [['component_id' => 99999]]])
        ->assertUnprocessable()->assertJsonValidationErrors('components.0.component_id');

    // tidak boleh berisi dirinya sendiri
    $this->putJson(route('v1.staff.products.update', $package), ['components' => [['component_id' => $package->id]]])
        ->assertUnprocessable()->assertJsonValidationErrors('components.0.component_id');

    // menu yang sudah jadi isi paket tidak boleh berubah jadi paket
    $this->putJson(route('v1.staff.products.update', $this->nasi), ['components' => [['component_id' => $this->teh->id]]])
        ->assertUnprocessable()->assertJsonValidationErrors('components');

    // isi yang sama dua kali ditolak
    ($this->makePackage)(['components' => [['component_id' => $this->ayam->id], ['component_id' => $this->ayam->id]]])
        ->assertUnprocessable();
});

test('a menu used inside a package cannot be deleted until the package changes', function () {
    $id = ($this->makePackage)(['name' => 'Paket Hemat'])->json('data.id');

    $this->deleteJson(route('v1.staff.products.destroy', $this->ayam))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('product');

    expect($this->ayam->refresh()->trashed())->toBeFalse();

    $this->putJson(route('v1.staff.products.update', $id), ['components' => [['component_id' => $this->nasi->id]]])->assertSuccessful();
    $this->deleteJson(route('v1.staff.products.destroy', $this->ayam))->assertSuccessful();

    // Paket yang sudah diarsipkan tidak menahan penghapusan isinya.
    $this->deleteJson(route('v1.staff.products.destroy', $id))->assertSuccessful();
    $this->deleteJson(route('v1.staff.products.destroy', $this->nasi))->assertSuccessful();
});

test('customer menu marks a package as unavailable when one of its contents is', function () {
    ($this->makePackage)(['name' => 'Paket Hemat'])->assertCreated();

    $menu = fn () => collect($this->getJson(route('v1.branches.menu', ['branch' => $this->branch, 'q' => 'Paket Hemat']))->json('data.0.products'))->first();

    expect($menu()['is_available'])->toBeTrue()
        ->and($menu()['components'])->toHaveCount(2);

    $this->nasi->update(['is_available' => false]);
    expect($menu()['is_available'])->toBeFalse();

    $this->nasi->update(['is_available' => true]);
    $this->ayam->delete();
    expect($menu()['is_available'])->toBeFalse();
});

test('a package created through the api is split into kitchen items when ordered', function () {
    $group = ProductAddonGroup::factory()->create(['product_id' => $this->ayam->id, 'name' => 'Tipe', 'is_required' => true, 'min_qty' => 1, 'max_qty' => 1]);
    ProductAddon::factory()->create(['group_id' => $group->id, 'name' => 'Bakar', 'price' => 0]);

    $created = ($this->makePackage)([
        'addon_groups' => [[
            'name' => 'Tipe Ayam', 'is_required' => true, 'min_qty' => 1, 'max_qty' => 1,
            'addons' => [['name' => 'Bakar', 'price' => 0], ['name' => 'Goreng', 'price' => 0]],
        ]],
    ])->assertCreated();

    $table = Table::factory()->create(['branch_id' => $this->branch->id]);
    $addonId = $created->json('data.addon_groups.0.addons.1.id');

    Sanctum::actingAs(User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']));
    $this->postJson(route('v1.orders.store'), [
        'branch_id' => $this->branch->id, 'order_type' => 'dine_in', 'table_token' => $table->qr_token, 'payment_method' => 'cash',
        'customer' => ['name' => 'Budi', 'phone' => '0812-3456-7890'],
        'items' => [['product_id' => $created->json('data.id'), 'qty' => 2, 'addon_ids' => [$addonId]]],
    ])->assertCreated()->assertJsonPath('data.total_amount', 50000);

    $children = Order::first()->items()->whereNotNull('parent_item_id')->get();
    expect($children->pluck('product_name')->all())->toBe(['Ayam Goreng', 'Nasi Putih'])
        ->and($children->first()->qty)->toBe(2)
        ->and($children->first()->addons->pluck('name')->all())->toBe(['Goreng'])
        ->and($children->last()->addons)->toHaveCount(0);
});
