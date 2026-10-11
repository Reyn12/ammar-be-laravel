<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductAddonGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->kasir = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);
    $this->category = Category::factory()->create(['name' => 'Makanan']);
    Sanctum::actingAs($this->kasir);

    $this->payload = fn (array $override = []) => array_merge([
        'category_id' => $this->category->id,
        'name' => 'Ayam Bakar',
        'price' => 28000,
        'is_available' => true,
        'addon_groups' => [
            [
                'name' => 'Level Pedas',
                'is_required' => true,
                'min_qty' => 1,
                'max_qty' => 1,
                'addons' => [['name' => 'Lv 0', 'price' => 0], ['name' => 'Lv 1', 'price' => 2000]],
            ],
            [
                'name' => 'Tambahan',
                'is_required' => false,
                'min_qty' => 0,
                'max_qty' => 2,
                'addons' => [['name' => 'Extra Sambal', 'price' => 3000], ['name' => 'Extra Nasi', 'price' => 5000]],
            ],
        ],
    ], $override);
});

test('categories are listed for the menu form', function () {
    Category::factory()->create(['name' => 'Minuman']);

    $this->getJson(route('v1.staff.categories'))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Makanan');
});

test('a product is created together with its variants and addons', function () {
    $this->postJson(route('v1.staff.products.store'), ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ayam Bakar')
        ->assertJsonPath('data.branch_id', $this->branch->id)
        ->assertJsonPath('data.category_name', 'Makanan')
        ->assertJsonCount(2, 'data.addon_groups')
        ->assertJsonPath('data.addon_groups.0.type', 'variant')
        ->assertJsonPath('data.addon_groups.1.type', 'addon')
        ->assertJsonPath('data.addon_groups.1.addons.1.price', 5000);

    expect(Product::count())->toBe(1)->and(ProductAddon::count())->toBe(4);
});

test('product list contains unavailable products of the own branch only', function () {
    Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id, 'name' => 'Habis', 'is_available' => false]);
    Product::factory()->create(['category_id' => $this->category->id, 'name' => 'Cabang Lain']);

    $names = collect($this->getJson(route('v1.staff.products.index'))->assertSuccessful()->json('data'))->pluck('name');

    expect($names->all())->toBe(['Habis']);
});

test('availability can be toggled with a partial update', function () {
    $product = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id, 'price' => 15000]);

    $this->putJson(route('v1.staff.products.update', $product), ['is_available' => false])
        ->assertSuccessful()
        ->assertJsonPath('data.is_available', false)
        ->assertJsonPath('data.price', 15000);
});

test('updating addon groups keeps, edits, adds and archives by id', function () {
    $created = $this->postJson(route('v1.staff.products.store'), ($this->payload)())->json('data');
    $pedas = $created['addon_groups'][0];
    $lv0 = $pedas['addons'][0];

    $this->putJson(route('v1.staff.products.update', $created['id']), [
        'addon_groups' => [[
            'id' => $pedas['id'],
            'name' => 'Tingkat Pedas',
            'is_required' => true,
            'min_qty' => 1,
            'max_qty' => 1,
            'addons' => [
                ['id' => $lv0['id'], 'name' => 'Tidak Pedas', 'price' => 0],           // diedit
                ['name' => 'Lv 3', 'price' => 4000],                                    // baru
                // Lv 1 tidak dikirim -> diarsipkan
            ],
        ]],
        // grup "Tambahan" tidak dikirim -> ikut diarsipkan
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.addon_groups')
        ->assertJsonPath('data.addon_groups.0.name', 'Tingkat Pedas')
        ->assertJsonPath('data.addon_groups.0.addons.0.name', 'Tidak Pedas')
        ->assertJsonCount(2, 'data.addon_groups.0.addons');

    // yang diarsipkan masih ada di database (riwayat), hanya tidak tampil
    expect(ProductAddonGroup::withTrashed()->count())->toBe(2)
        ->and(ProductAddon::withTrashed()->count())->toBe(5)
        ->and(ProductAddon::count())->toBe(2);
});

test('ids of another product cannot be hijacked through addon groups', function () {
    $other = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id]);
    $foreignGroup = ProductAddonGroup::factory()->create(['product_id' => $other->id]);
    $mine = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id]);

    $this->putJson(route('v1.staff.products.update', $mine), ['addon_groups' => [[
        'id' => $foreignGroup->id,
        'name' => 'Curian',
        'addons' => [['name' => 'x', 'price' => 0]],
    ]]])->assertUnprocessable();

    expect($foreignGroup->refresh()->name)->not->toBe('Curian');
});

test('product validation rejects bad data', function () {
    $this->postJson(route('v1.staff.products.store'), ['name' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id', 'name', 'price']);

    $this->postJson(route('v1.staff.products.store'), ($this->payload)(['price' => -1]))->assertUnprocessable()->assertJsonValidationErrors('price');

    // min > max, dan max melebihi jumlah opsi
    $bad = ($this->payload)(['addon_groups' => [['name' => 'G', 'is_required' => true, 'min_qty' => 3, 'max_qty' => 2, 'addons' => [['name' => 'a'], ['name' => 'b'], ['name' => 'c']]]]]);
    $this->postJson(route('v1.staff.products.store'), $bad)->assertUnprocessable()->assertJsonValidationErrors('addon_groups.0.min_qty');

    $tooMany = ($this->payload)(['addon_groups' => [['name' => 'G', 'max_qty' => 5, 'addons' => [['name' => 'a']]]]]);
    $this->postJson(route('v1.staff.products.store'), $tooMany)->assertUnprocessable()->assertJsonValidationErrors('addon_groups.0.max_qty');

    $noAddons = ($this->payload)(['addon_groups' => [['name' => 'G', 'addons' => []]]]);
    $this->postJson(route('v1.staff.products.store'), $noAddons)->assertUnprocessable();
});

test('a local device path is rejected as image url but uploaded urls are accepted', function () {
    $this->postJson(route('v1.staff.products.store'), ($this->payload)(['image_url' => '/data/user/0/app/cache/foto.jpg']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.image_url.0', 'Foto belum diunggah. Unggah dulu lalu kirim URL hasilnya.');

    $this->postJson(route('v1.staff.products.store'), ($this->payload)(['image_url' => 'storage/products/abc.jpg']))
        ->assertCreated()
        ->assertJsonPath('data.image_url', asset('storage/products/abc.jpg'));

    $this->postJson(route('v1.staff.products.store'), ($this->payload)(['name' => 'Lain', 'image_url' => 'https://cdn.example.com/a.png']))
        ->assertCreated()
        ->assertJsonPath('data.image_url', 'https://cdn.example.com/a.png');
});

test('product images are uploaded to the public disk', function () {
    Storage::fake('public');

    $response = $this->postJson(route('v1.staff.uploads.product-image'), [
        'image' => UploadedFile::fake()->image('menu.jpg', 600, 600),
    ])->assertCreated()->assertJsonStructure(['data' => ['path', 'url']]);

    $path = $response->json('data.path');
    expect($path)->toStartWith('storage/products/');
    Storage::disk('public')->assertExists(str_replace('storage/', '', $path));

    $this->postJson(route('v1.staff.uploads.product-image'), ['image' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])
        ->assertUnprocessable();
    $this->postJson(route('v1.staff.uploads.product-image'), ['image' => UploadedFile::fake()->image('besar.jpg')->size(4096)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.image.0', 'Ukuran foto maksimal 2 MB.');
});

test('deleting a product archives it and keeps the order history intact', function () {
    $product = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id, 'name' => 'Ayam Lama']);
    $group = ProductAddonGroup::factory()->create(['product_id' => $product->id]);
    $addon = ProductAddon::factory()->create(['group_id' => $group->id, 'name' => 'Lv 2']);

    $order = Order::factory()->create(['branch_id' => $this->branch->id, 'payment_status' => 'paid']);
    $item = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Ayam Lama']);
    OrderItemAddon::factory()->create(['order_item_id' => $item->id, 'addon_id' => $addon->id, 'name' => 'Lv 2']);

    $this->deleteJson(route('v1.staff.products.destroy', $product))->assertSuccessful();

    // hilang dari daftar staf dan menu customer
    expect(collect($this->getJson(route('v1.staff.products.index'))->json('data'))->pluck('id')->all())->not->toContain($product->id);
    $this->getJson(route('v1.branches.menu', $this->branch))->assertJsonCount(0, 'data');

    // tetapi riwayat pesanan utuh
    expect(OrderItem::count())->toBe(1)
        ->and(OrderItemAddon::count())->toBe(1)
        ->and($item->refresh()->product->name)->toBe('Ayam Lama');

    $this->getJson(route('v1.staff.orders.show', $order))
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.product_name', 'Ayam Lama');
});

test('archived products cannot be ordered by customers', function () {
    $product = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id]);
    $product->delete();

    $this->postJson(route('v1.orders.store'), [
        'branch_id' => $this->branch->id,
        'order_type' => 'takeaway',
        'payment_method' => 'cash',
        'customer' => ['name' => 'Budi', 'phone' => '081234567890'],
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
});

test('menu management is limited to cashier and owner of the same branch', function () {
    $product = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id]);
    $other = Product::factory()->create(['category_id' => $this->category->id]);

    $this->putJson(route('v1.staff.products.update', $other), ['name' => 'Curian'])->assertNotFound();
    $this->deleteJson(route('v1.staff.products.destroy', $other))->assertNotFound();

    app('auth')->forgetGuards();
    Sanctum::actingAs(User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'kitchen']));

    $this->getJson(route('v1.staff.products.index'))->assertForbidden();
    $this->putJson(route('v1.staff.products.update', $product), ['name' => 'x'])->assertForbidden();
});

test('a plain drink without options can gain options later when edited', function () {
    $water = Product::factory()->create(['branch_id' => $this->branch->id, 'category_id' => $this->category->id, 'name' => 'Air Mineral']);
    expect($water->addonGroups()->count())->toBe(0);

    $this->putJson(route('v1.staff.products.update', $water), [
        'addon_groups' => [[
            'name' => 'Penyajian', 'is_required' => true, 'min_qty' => 1, 'max_qty' => 1,
            'addons' => [['name' => 'Dingin', 'price' => 0], ['name' => 'Biasa', 'price' => 0]],
        ]],
    ])->assertSuccessful()->assertJsonPath('data.addon_groups.0.type', 'variant');

    expect($water->refresh()->addonGroups()->first()->addons()->count())->toBe(2);

    // Dan bisa dikosongkan lagi: grup diarsipkan, menunya kembali tanpa pilihan.
    $this->putJson(route('v1.staff.products.update', $water), ['addon_groups' => []])->assertSuccessful();
    expect($water->addonGroups()->count())->toBe(0);
});
