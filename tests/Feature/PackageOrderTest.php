<?php

use App\Actions\Kitchen\AssignOrderToBatches;
use App\Models\Batch;
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
    $this->table = Table::factory()->create(['branch_id' => $this->branch->id]);
    $this->koki = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'kitchen']);
    $category = Category::factory()->create();

    $make = fn (string $name, int $price) => Product::factory()->create([
        'branch_id' => $this->branch->id, 'category_id' => $category->id, 'name' => $name, 'price' => $price,
    ]);

    $this->ayam = $make('Ayam Bakar / Goreng 1 Potong', 17000);
    $this->nasi = $make('Nasi Putih Porsi', 5000);
    $this->teh = $make('Es Teh Manis', 5000);
    $this->paket = $make('Paket Hemat 3', 28000);

    $group = ProductAddonGroup::factory()->create([
        'product_id' => $this->paket->id, 'name' => 'Tipe Ayam', 'is_required' => true, 'min_qty' => 1, 'max_qty' => 1,
    ]);
    $this->goreng = ProductAddon::factory()->create(['group_id' => $group->id, 'name' => 'Goreng', 'price' => 0]);

    ProductComponent::create(['product_id' => $this->paket->id, 'component_id' => $this->ayam->id, 'qty' => 1, 'receives_options' => true]);
    ProductComponent::create(['product_id' => $this->paket->id, 'component_id' => $this->nasi->id, 'qty' => 1]);
    ProductComponent::create(['product_id' => $this->paket->id, 'component_id' => $this->teh->id, 'qty' => 1]);

    $this->orderPaket = fn (int $qty = 1) => $this->postJson(route('v1.orders.store'), [
        'branch_id' => $this->branch->id,
        'order_type' => 'dine_in',
        'table_token' => $this->table->qr_token,
        'payment_method' => 'cash',
        'customer' => ['name' => 'Budi', 'phone' => '0812-3456-7890', 'email' => null],
        'items' => [[
            'product_id' => $this->paket->id,
            'qty' => $qty,
            'notes' => 'jangan pedas',
            'addon_ids' => [$this->goreng->id],
        ]],
    ]);

    $this->paidPackageOrder = function (int $qty = 1) {
        ($this->orderPaket)($qty)->assertCreated();
        $order = Order::latest('id')->first();
        $order->update(['payment_status' => 'paid']);
        app(AssignOrderToBatches::class)->handle($order);

        return $order->refresh();
    };
});

test('a package is split into free kitchen items while the customer pays the package price', function () {
    ($this->orderPaket)(2)
        ->assertCreated()
        ->assertJsonPath('data.total_amount', 56000)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product_name', 'Paket Hemat 3')
        ->assertJsonPath('data.items.0.components.0.product_name', 'Ayam Bakar / Goreng 1 Potong')
        ->assertJsonPath('data.items.0.components.0.qty', 2)
        ->assertJsonCount(3, 'data.items.0.components');

    $order = Order::first();
    $children = $order->items()->whereNotNull('parent_item_id')->get();

    expect($order->items()->count())->toBe(4)
        ->and($children->sum('price'))->toBe(0)
        ->and($children->pluck('product_name')->all())->toBe(['Ayam Bakar / Goreng 1 Potong', 'Nasi Putih Porsi', 'Es Teh Manis']);

    // Pilihan dan catatan paket hanya ikut ke ayam, bukan ke nasi atau minuman.
    $ayam = $children->firstWhere('product_id', $this->ayam->id);
    expect($ayam->notes)->toBe('jangan pedas')
        ->and($ayam->addons->pluck('name')->all())->toBe(['Goreng'])
        ->and($children->firstWhere('product_id', $this->nasi->id)->addons)->toHaveCount(0);
});

test('a package cannot be ordered when one of its contents is unavailable', function () {
    $this->teh->update(['is_available' => false]);

    ($this->orderPaket)()
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.product_id');

    expect(Order::count())->toBe(0);
});

test('kitchen batches group package contents with regular orders and skip the package line', function () {
    ($this->paidPackageOrder)(2);

    expect(Batch::count())->toBe(3)
        ->and(Batch::where('product_id', $this->paket->id)->exists())->toBeFalse()
        ->and(Batch::where('product_id', $this->ayam->id)->value('total_qty'))->toBe(2);

    Sanctum::actingAs($this->koki);
    $this->getJson(route('v1.kitchen.batches'))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.product_name', 'Ayam Bakar / Goreng 1 Potong');
});

test('package status follows its contents and the order completes when all are ready', function () {
    $order = ($this->paidPackageOrder)();
    $package = $order->items()->whereNull('parent_item_id')->first();

    Sanctum::actingAs($this->koki);
    $this->postJson(route('v1.kitchen.batches.process', Batch::where('product_id', $this->ayam->id)->first()))->assertSuccessful();

    expect($package->refresh()->status)->toBe('cooking')
        ->and($order->refresh()->status)->toBe('cooking');

    $this->patchJson(route('v1.order-items.status', $package), ['status' => 'cooking'])->assertSuccessful();
    $this->patchJson(route('v1.order-items.status', $package), ['status' => 'ready'])->assertSuccessful();

    expect($order->items()->pluck('status')->unique()->all())->toBe(['ready'])
        ->and($order->refresh()->status)->toBe('completed');
});

test('staff order resource lists the package once with its components', function () {
    ($this->paidPackageOrder)();

    Sanctum::actingAs($this->koki);
    $this->getJson(route('v1.kitchen.orders'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.0.items')
        ->assertJsonPath('data.0.items.0.product_name', 'Paket Hemat 3')
        ->assertJsonPath('data.0.items.0.components.0.addons.0.name', 'Goreng')
        ->assertJsonPath('data.0.items.0.components.0.addons.0.group_name', 'Tipe Ayam')
        ->assertJsonPath('data.0.items.0.components.0.notes', 'jangan pedas');
});
