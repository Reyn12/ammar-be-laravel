<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StaffEvent;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->kasir = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'cashier']);
    $this->koki = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'kitchen']);
    $this->table = Table::factory()->create(['branch_id' => $this->branch->id, 'table_number' => 7]);

    $this->makeOrder = function (array $attributes = []) {
        $order = Order::factory()->create(array_merge([
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'total_amount' => 50000,
            'customer_name' => 'Budi',
            'customer_phone' => '081234567890',
        ], $attributes));

        $product = Product::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => Category::factory()->create(['name' => 'Makanan']),
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Ayam Bakar',
            'qty' => 2,
            'price' => 25000,
            'notes' => 'tidak pedas',
        ]);
        OrderItemAddon::factory()->create(['order_item_id' => $item->id, 'name' => 'Lv 1', 'price' => 0]);

        return $order;
    };

    $this->openShift = fn (?User $user = null) => Shift::create([
        'user_id' => ($user ?? $this->kasir)->id,
        'start_time' => now(),
        'status' => 'active',
        'starting_cash' => 100000,
    ]);
});

test('incoming orders list contains paid and pending cash orders only for the own branch', function () {
    $pendingCash = ($this->makeOrder)();
    $paidQris = ($this->makeOrder)(['payment_method' => 'qris', 'payment_status' => 'paid']);
    $completed = ($this->makeOrder)(['payment_status' => 'paid', 'status' => 'completed']);
    $cancelled = ($this->makeOrder)(['payment_method' => 'qris', 'payment_status' => 'expired', 'status' => 'cancelled']);
    $unpaidQris = ($this->makeOrder)(['payment_method' => 'qris', 'payment_status' => 'unpaid']);
    $otherBranch = Order::factory()->create(['payment_method' => 'cash', 'payment_status' => 'unpaid']);

    Sanctum::actingAs($this->kasir);

    $ids = collect($this->getJson(route('v1.staff.orders.index', ['status' => 'paid,pending_cash']))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->json('data'))->pluck('id');

    expect($ids)->toContain($pendingCash->id, $paidQris->id)
        ->not->toContain($completed->id, $cancelled->id, $unpaidQris->id, $otherBranch->id);

    $history = $this->getJson(route('v1.staff.orders.index', ['status' => 'completed']))->json('data');
    expect(collect($history)->pluck('id')->all())->toBe([$completed->id]);
});

test('order payload matches what the flutter app expects', function () {
    $order = ($this->makeOrder)();
    Sanctum::actingAs($this->kasir);

    $this->getJson(route('v1.staff.orders.show', $order))
        ->assertSuccessful()
        ->assertJsonPath('data.code', $order->code)
        ->assertJsonPath('data.table_number', '7')
        ->assertJsonPath('data.customer_name', 'Budi')
        ->assertJsonPath('data.items.0.product_name', 'Ayam Bakar')
        ->assertJsonPath('data.items.0.category_name', 'Makanan')
        ->assertJsonPath('data.items.0.notes', 'tidak pedas')
        ->assertJsonPath('data.items.0.addons.0.name', 'Lv 1');
});

test('only cashier and owner can read orders, and not from another branch', function () {
    $order = ($this->makeOrder)();

    $this->getJson(route('v1.staff.orders.index'))->assertUnauthorized();

    Sanctum::actingAs($this->koki);
    $this->getJson(route('v1.staff.orders.index'))->assertForbidden();

    $outsider = User::factory()->create(['role' => 'cashier']);
    Sanctum::actingAs($outsider);
    $this->getJson(route('v1.staff.orders.show', $order))->assertNotFound();
});

test('cash confirmation needs an open shift', function () {
    $order = ($this->makeOrder)();
    Sanctum::actingAs($this->kasir);

    $this->postJson(route('v1.staff.orders.confirm-cash', $order))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Buka shift dulu sebelum mengonfirmasi pembayaran tunai.');
});

test('confirming cash marks the order paid, records the cashier and sends the receipt', function () {
    config()->set('services.fonnte', ['base_url' => 'https://fonnte.test', 'token' => 'tok']);
    Http::fake(['fonnte.test/send' => Http::response(['status' => true])]);

    $shift = ($this->openShift)();
    $order = ($this->makeOrder)();
    Sanctum::actingAs($this->kasir);

    $this->postJson(route('v1.staff.orders.confirm-cash', $order), ['received_amount' => 100000])
        ->assertSuccessful()
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.cash_received', 100000);

    $order->refresh();
    expect($order->confirmed_by)->toBe($this->kasir->id)
        ->and($order->shift_id)->toBe($shift->id)
        ->and($order->paid_at)->not->toBeNull();

    $this->assertDatabaseHas('notifications', ['order_id' => $order->id, 'status' => 'sent']);
});

test('cash confirmation rejects bad cases', function () {
    ($this->openShift)();
    Sanctum::actingAs($this->kasir);

    $order = ($this->makeOrder)();
    $this->postJson(route('v1.staff.orders.confirm-cash', $order), ['received_amount' => 10000])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Uang yang diterima kurang dari total tagihan.');

    $qris = ($this->makeOrder)(['payment_method' => 'qris']);
    $this->postJson(route('v1.staff.orders.confirm-cash', $qris))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Pesanan ini tidak dibayar tunai.');

    $paid = ($this->makeOrder)(['payment_status' => 'paid']);
    $this->postJson(route('v1.staff.orders.confirm-cash', $paid))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Pesanan ini sudah dikonfirmasi lunas.');

    $cancelled = ($this->makeOrder)(['status' => 'cancelled']);
    $this->postJson(route('v1.staff.orders.confirm-cash', $cancelled))->assertUnprocessable();

    expect(Notification::count())->toBe(0);
});

test('customer order lookup by code still works next to the staff routes', function () {
    $order = ($this->makeOrder)();

    $this->getJson(route('v1.orders.show', $order->code))->assertSuccessful()->assertJsonPath('data.code', $order->code);
});

test('ready filter lists completed orders that are not handed over yet', function () {
    $ready = ($this->makeOrder)(['payment_status' => 'paid', 'status' => 'completed']);
    ($this->makeOrder)(['payment_status' => 'paid', 'status' => 'completed', 'handed_over_at' => now()]);
    ($this->makeOrder)(['payment_status' => 'paid', 'status' => 'cooking']);
    Sanctum::actingAs($this->kasir);

    $this->getJson(route('v1.staff.orders.index', ['status' => 'ready']))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ready->id)
        ->assertJsonPath('data.0.handed_over_at', null);
});

test('handing over a completed order stamps it once and emits an event', function () {
    $order = ($this->makeOrder)(['payment_status' => 'paid', 'status' => 'completed']);
    Sanctum::actingAs($this->kasir);

    $this->postJson(route('v1.staff.orders.hand-over', $order))
        ->assertSuccessful()
        ->assertJsonPath('data.handed_over_at', fn ($value) => $value !== null);

    $stamp = $order->refresh()->handed_over_at;
    $this->postJson(route('v1.staff.orders.hand-over', $order))->assertSuccessful(); // idempoten

    expect($order->refresh()->handed_over_at->equalTo($stamp))->toBeTrue()
        ->and(StaffEvent::where('type', StaffEvent::ORDER_HANDED_OVER)->count())->toBe(1);
});

test('an order that is still cooking cannot be handed over', function () {
    $order = ($this->makeOrder)(['payment_status' => 'paid', 'status' => 'cooking']);
    Sanctum::actingAs($this->kasir);

    $this->postJson(route('v1.staff.orders.hand-over', $order))->assertUnprocessable();

    expect($order->refresh()->handed_over_at)->toBeNull();
});

test('hand over is limited to the same branch and to cashier or owner', function () {
    $order = ($this->makeOrder)(['payment_status' => 'paid', 'status' => 'completed']);

    Sanctum::actingAs($this->koki);
    $this->postJson(route('v1.staff.orders.hand-over', $order))->assertForbidden();

    Sanctum::actingAs(User::factory()->create(['role' => 'cashier']));
    $this->postJson(route('v1.staff.orders.hand-over', $order))->assertNotFound();
});
